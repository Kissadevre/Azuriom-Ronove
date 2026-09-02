<?php

namespace Azuriom\Plugin\Ronove\Services;

use Azuriom\Models\Setting;
use Azuriom\Plugin\Ronove\Providers\Resources\ThemeSettingResourceProvider;
use Illuminate\Http\Request;

class LocalizedThemeConfiguration
{
    public function __construct(
        private readonly ThemeTranslationRegistrar $themes,
        private readonly ResourceRegistry $resources,
        private readonly TranslationResolver $translations,
    ) {}

    public function apply(Request $request): void
    {
        $manifest = $this->themes->registerActive();

        if ($manifest === null) {
            return;
        }

        $setting = Setting::query()->where('name', $manifest->settingName)->first();
        $config = $setting?->value;

        if (! is_array($config)) {
            return;
        }

        // Always restore the stored source first so long-running workers never
        // leak one visitor's translated configuration into another request.
        config()->set('theme', $config);

        if ($request->routeIs('admin.*', '*.admin.*') || $request->expectsJson()) {
            return;
        }

        $models = [];

        foreach ($manifest->blocks as $block) {
            $type = $manifest->providerType($block->id);

            if ($this->resources->has($type)) {
                $models[$type] = $setting;
            }
        }

        foreach ($this->translations->valuesForResources($models) as $type => $values) {
            $provider = $this->resources->get($type);

            if (! $provider instanceof ThemeSettingResourceProvider) {
                continue;
            }

            foreach ($values as $field => $value) {
                data_set($config, $provider->path($field), $value);
            }
        }

        config()->set('theme', $config);
    }
}
