<?php

namespace Azuriom\Plugin\Ronove\Services;

use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Support\LocaleCode;
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
            LocaleCode::normalize($locale ?? app()->getLocale()),
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

        foreach ($models as $model) {
            $translations = $resources->get($provider->key($model))?->translations ?? collect();

            foreach ($this->resolveValues($provider, $model, $translations, $locale) as $field => $value) {
                $model->setAttribute($field, $value);
            }
        }
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
     * @return array<string, string|null>
     */
    private function resolveValues(
        ResourceProvider $provider,
        Model $model,
        Collection $translations,
        string $locale,
    ): array {
        $published = $translations->filter->isPublished();
        $selected = $published->first(fn (Translation $translation) => $translation->locale?->code === $locale);
        $globalCode = $this->locales->globalLocale();
        $global = $globalCode === $locale
            ? null
            : $published->first(fn (Translation $translation) => $translation->locale?->code === $globalCode);
        $values = [];

        foreach ($provider->fields() as $field => $definition) {
            $translated = $this->filledValue($selected, $field)
                ?? $this->filledValue($global, $field);
            $values[$field] = $translated ?? $provider->original($model, $field);
        }

        return $values;
    }

    private function filledValue(?Translation $translation, string $field): ?string
    {
        $value = $translation?->values[$field] ?? null;

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
