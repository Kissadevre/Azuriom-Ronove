<?php

namespace Azuriom\Plugin\Ronove\Services;

use Azuriom\Plugin\Ronove\Providers\Resources\ThemeSettingResourceProvider;
use Azuriom\Plugin\Ronove\Support\ThemeTranslationManifest;
use Azuriom\Plugin\Ronove\Support\TranslationIntegration;
use Throwable;

class ThemeTranslationRegistrar
{
    private ?ThemeTranslationManifest $registered = null;

    public function __construct(
        private readonly ThemeTranslationManifestLoader $manifests,
        private readonly ResourceRegistry $resources,
    ) {}

    public function registerActive(): ?ThemeTranslationManifest
    {
        if ($this->registered !== null) {
            return $this->registered;
        }

        try {
            $manifest = $this->manifests->active();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if ($manifest === null) {
            return null;
        }

        if (! $this->resources->hasIntegration($manifest->integrationId)) {
            $this->resources->registerIntegration(new TranslationIntegration(
                $manifest->integrationId,
                $manifest->label,
                $manifest->icon,
                $manifest->permission,
                $manifest->order,
            ));
        }

        foreach ($manifest->blocks as $block) {
            $provider = new ThemeSettingResourceProvider($manifest, $block);

            if (! $this->resources->has($provider->type())) {
                $this->resources->register($provider, $manifest->integrationId);
            }
        }

        return $this->registered = $manifest;
    }
}
