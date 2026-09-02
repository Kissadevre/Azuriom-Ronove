<?php

namespace Azuriom\Plugin\Ronove\Services;

use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Support\LocaleCode;
use Azuriom\Plugin\Ronove\Support\TranslationPreviewField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class TranslationResolver
{
    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly LocaleManager $locales,
    ) {}

    public function translate(string $type, Model $model, string $field, ?string $locale = null): ?string
    {
        $values = $this->values($type, $model, $locale);

        if (! array_key_exists($field, $values)) {
            throw new InvalidArgumentException("Unknown translatable field [{$type}.{$field}].");
        }

        return $values[$field];
    }

    /**
     * @return array<string, string|null>
     */
    public function values(string $type, Model $model, ?string $locale = null): array
    {
        $provider = $this->registry->get($type);
        $this->assertModel($provider, $model);
        $resource = Resource::query()
            ->where('resource_type', $type)
            ->where('resource_key', $provider->key($model))
            ->with(['translations.locale'])
            ->first();

        return $this->resolveValues(
            $provider,
            $model,
            $resource?->translations ?? collect(),
            $this->locales->translationChain(LocaleCode::normalize($locale ?? app()->getLocale())),
        );
    }

    /**
     * Apply published translations to models that will be rendered in a view.
     *
     * @param  iterable<int, Model>  $models
     */
    public function overlay(string $type, iterable $models, ?string $locale = null): void
    {
        $provider = $this->registry->get($type);
        $modelClass = $provider->model();
        $models = collect($models)->filter(fn ($model) => $model instanceof $modelClass)->values();

        if ($models->isEmpty()) {
            return;
        }

        $resources = Resource::query()
            ->where('resource_type', $type)
            ->whereIn('resource_key', $models->map(fn (Model $model) => $provider->key($model)))
            ->with(['translations.locale'])
            ->get()
            ->keyBy('resource_key');
        $locale = LocaleCode::normalize($locale ?? app()->getLocale());
        $chain = $this->locales->translationChain($locale);

        foreach ($models as $model) {
            $translations = $resources->get($provider->key($model))?->translations ?? collect();

            foreach ($this->resolveValues($provider, $model, $translations, $chain) as $field => $value) {
                $model->setAttribute($field, $value);
            }
        }
    }

    /**
     * Resolve unsaved values exactly as they would appear if published.
     *
     * @param  array<string, string>  $values
     * @return array<string, TranslationPreviewField>
     */
    public function preview(string $type, Model $model, string $locale, array $values): array
    {
        $provider = $this->registry->get($type);
        $this->assertModel($provider, $model);
        $resource = Resource::query()
            ->where('resource_type', $type)
            ->where('resource_key', $provider->key($model))
            ->with(['translations.locale'])
            ->first();
        $published = ($resource?->translations ?? collect())->filter->hasPublishedVersion();
        $selected = LocaleCode::normalize($locale);
        $fallbackChain = array_slice($this->locales->translationChain($selected), 1);
        $global = $this->locales->globalLocale();
        $preview = [];

        foreach (array_keys($provider->fields()) as $field) {
            $original = $provider->original($model, $field);
            $value = $this->filledArrayValue($values, $field);

            if ($value !== null) {
                $preview[$field] = new TranslationPreviewField(
                    $original,
                    $value,
                    TranslationPreviewField::SELECTED,
                    $selected,
                );

                continue;
            }

            [$resolved, $sourceLocale] = $this->publishedValue($published, $field, $fallbackChain);

            if ($sourceLocale !== null) {
                $preview[$field] = new TranslationPreviewField(
                    $original,
                    $resolved,
                    $sourceLocale === $global
                        ? TranslationPreviewField::GLOBAL
                        : TranslationPreviewField::FALLBACK,
                    $sourceLocale,
                );

                continue;
            }

            $preview[$field] = new TranslationPreviewField(
                $original,
                $original,
                TranslationPreviewField::ORIGINAL,
            );
        }

        return $preview;
    }

    public function sourceHash(ResourceProvider $provider, Model $model): string
    {
        $values = [];

        foreach (array_keys($provider->fields()) as $field) {
            $values[$field] = $provider->original($model, $field);
        }

        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * @param  Collection<int, Translation>  $translations
     * @param  array<int, string>  $localeChain
     * @return array<string, string|null>
     */
    private function resolveValues(
        ResourceProvider $provider,
        Model $model,
        Collection $translations,
        array $localeChain,
    ): array {
        $published = $translations->filter->hasPublishedVersion();
        $values = [];

        foreach (array_keys($provider->fields()) as $field) {
            [$translated] = $this->publishedValue($published, $field, $localeChain);
            $values[$field] = $translated ?? $provider->original($model, $field);
        }

        return $values;
    }

    /**
     * @param  Collection<int, Translation>  $translations
     * @param  array<int, string>  $localeChain
     * @return array{0: string|null, 1: string|null}
     */
    private function publishedValue(Collection $translations, string $field, array $localeChain): array
    {
        foreach ($localeChain as $locale) {
            if ($locale === $this->locales->globalLocale()) {
                continue;
            }

            $translation = $translations->first(
                fn (Translation $translation) => $translation->locale?->code === $locale
            );
            $value = $this->filledValue($translation, $field);

            if ($value !== null) {
                return [$value, $locale];
            }
        }

        return [null, null];
    }

    private function filledValue(?Translation $translation, string $field): ?string
    {
        $value = $translation?->publicValues()[$field] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function filledArrayValue(array $values, string $field): ?string
    {
        $value = $values[$field] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function assertModel(ResourceProvider $provider, Model $model): void
    {
        $modelClass = $provider->model();

        if (! $model instanceof $modelClass) {
            throw new InvalidArgumentException(
                'The model ['.$model::class.'] is not supported by ['.$provider->type().'].'
            );
        }
    }
}
