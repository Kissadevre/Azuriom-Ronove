<?php

namespace Azuriom\Plugin\Ronove;

use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Services\LanguageSwitcher;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Services\TranslationResolver;
use Azuriom\Plugin\Ronove\Support\LocaleOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class RonoveManager
{
    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly TranslationResolver $translations,
        private readonly LanguageSwitcher $languageSwitcher,
    ) {}

    public function registerResourceType(ResourceProvider $provider): void
    {
        $this->registry->register($provider);
    }

    public function resources(): ResourceRegistry
    {
        return $this->registry;
    }

    /**
     * Return enabled locales without exposing Ronove's persistence models.
     *
     * @return Collection<int, LocaleOption>
     */
    public function languageOptions(): Collection
    {
        return $this->languageSwitcher->options();
    }

    public function currentLanguage(): ?LocaleOption
    {
        return $this->languageSwitcher->current();
    }

    public function languageUpdateUrl(): string
    {
        return $this->languageSwitcher->updateUrl();
    }

    public function translate(string $type, Model $resource, string $field, ?string $locale = null): ?string
    {
        return $this->translations->translate($type, $resource, $field, $locale);
    }

    /**
     * @return array<string, string|null>
     */
    public function translatedValues(string $type, Model $resource, ?string $locale = null): array
    {
        return $this->translations->values($type, $resource, $locale);
    }

    public function forget(string $type, Model $resource): void
    {
        $provider = $this->registry->get($type);

        Resource::query()
            ->where('resource_type', $type)
            ->where('resource_key', $provider->key($resource))
            ->delete();
    }
}
