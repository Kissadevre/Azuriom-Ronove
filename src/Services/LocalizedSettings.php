<?php

namespace Azuriom\Plugin\Ronove\Services;

use Azuriom\Models\Setting;
use Illuminate\Http\Request;

class LocalizedSettings
{
    private const TYPES = [
        'home_message' => 'core.site-message',
        'welcome_alert' => 'core.site-message',
        'maintenance.message' => 'core.site-message',
        'conditions' => 'core.registration-conditions',
        'copyright' => 'core.footer',
    ];

    public function __construct(private readonly TranslationResolver $translations) {}

    public function apply(Request $request): void
    {
        $settings = Setting::query()
            ->whereIn('name', array_keys(self::TYPES))
            ->get()
            ->keyBy('name');

        foreach (array_keys(self::TYPES) as $name) {
            setting()->set($name, $settings->get($name)?->value);
        }

        if ($request->routeIs('admin.*', '*.admin.*') || $request->expectsJson()) {
            return;
        }

        $groups = collect(self::TYPES)->mapToGroups(
            fn (string $type, string $name) => [$type => $name]
        );

        foreach ($groups as $type => $names) {
            $models = $names
                ->map(fn (string $name) => $settings->get($name))
                ->filter(fn ($setting) => $setting instanceof Setting
                    && ($setting->name !== 'conditions' || ! $this->isUrl($setting->value)))
                ->values();

            $this->translations->overlay($type, $models);

            foreach ($models as $setting) {
                setting()->set($setting->name, $setting->getAttribute('content'));
            }
        }
    }

    private function isUrl(mixed $value): bool
    {
        return is_string($value) && preg_match('/^https?:\/\//i', $value) === 1;
    }
}
