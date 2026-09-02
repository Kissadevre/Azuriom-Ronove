<?php

namespace Azuriom\Plugin\Ronove;

use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Services\LanguageSwitcher;
use Azuriom\Plugin\Ronove\Services\PublicLanguagePage;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Services\ReviewWorkflow;
use Azuriom\Plugin\Ronove\Services\TranslationCoverage;
use Azuriom\Plugin\Ronove\Services\TranslationResolver;
use Azuriom\Plugin\Ronove\Support\LocaleOption;
use Azuriom\Plugin\Ronove\Support\TranslationCoverageReport;
use Azuriom\Plugin\Ronove\Support\TranslationIntegration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class RonoveManager
{
    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly TranslationResolver $translations,
        private readonly LanguageSwitcher $languageSwitcher,
        private readonly TranslationCoverage $translationCoverage,
        private readonly ReviewWorkflow $reviewWorkflow,
        private readonly PublicLanguagePage $publicLanguagePage,
    ) {}

    public function registerIntegration(
        string $id,
        string $name,
        string $icon = 'bi bi-puzzle',
        ?string $permission = null,
        int $order = 100,
    ): void {
        $this->registry->registerIntegration(new TranslationIntegration(
            $id,
            $name,
            $icon,
            $permission,
            $order,
        ));
    }

    public function registerResourceType(ResourceProvider $provider, ?string $integration = null): void
    {
        $this->registry->register($provider, $integration);
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

    public function reviewWorkflowEnabled(): bool
    {
        return $this->reviewWorkflow->enabled();
    }

    public function publicLanguagePageEnabled(): bool
    {
        return $this->publicLanguagePage->enabled();
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

    /**
     * Apply published translations to models for the current request only.
     *
     * @param  iterable<int, Model>  $resources
     */
    public function overlay(string $type, iterable $resources, ?string $locale = null): void
    {
        $this->translations->overlay($type, $resources, $locale);
    }

    public function coverage(string $type, string $locale): TranslationCoverageReport
    {
        $localeModel = Locale::query()
            ->where('code', $locale)
            ->where('is_enabled', true)
            ->translationTargets()
            ->first();

        if ($localeModel === null) {
            throw new InvalidArgumentException("Unknown enabled Ronove locale [{$locale}].");
        }

        return $this->translationCoverage->report($this->registry->get($type), $localeModel);
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
