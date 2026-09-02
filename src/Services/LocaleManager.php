<?php

namespace Azuriom\Plugin\Ronove\Services;

use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\UserPreference;
use Azuriom\Plugin\Ronove\Support\LocaleCode;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LocaleManager
{
    public const SESSION_KEY = 'ronove_locale';

    public const COOKIE_NAME = 'ronove_locale';

    public function globalLocale(): string
    {
        return LocaleCode::normalize(setting('locale', config('app.locale', 'en')));
    }

    /**
     * @return Collection<int, Locale>
     */
    public function enabled(): Collection
    {
        return Locale::query()
            ->where('is_enabled', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function resolve(Request $request): string
    {
        $enabled = $this->enabled();

        if ($enabled->isEmpty()) {
            return $this->globalLocale();
        }

        $enabledByCode = $enabled->keyBy('code');

        if ($request->user() !== null) {
            $preference = UserPreference::query()
                ->with('locale')
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->first();

            if ($preference?->locale?->is_enabled === true
                && $enabledByCode->has($preference->locale->code)) {
                return $preference->locale->code;
            }
        }

        foreach ([$request->session()->get(self::SESSION_KEY), $request->cookie(self::COOKIE_NAME)] as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            $normalized = LocaleCode::normalize($candidate);

            if ($enabledByCode->has($normalized)) {
                return $normalized;
            }
        }

        foreach ($request->getLanguages() as $preferred) {
            $normalized = LocaleCode::normalize($preferred);

            if ($enabledByCode->has($normalized)) {
                return $normalized;
            }

            $languageMatch = $enabled->first(
                fn (Locale $locale) => LocaleCode::language($locale->code) === LocaleCode::language($normalized)
            );

            if ($languageMatch !== null) {
                return $languageMatch->code;
            }
        }

        $global = $this->globalLocale();

        return $enabledByCode->has($global) ? $global : $enabled->first()->code;
    }

    public function apply(Request $request): string
    {
        $locale = $this->resolve($request);
        $fallback = $this->globalLocale();

        app()->setLocale($locale);
        app('translator')->setFallback($fallback);
        Carbon::setLocale($locale);
        $request->attributes->set(self::SESSION_KEY, $locale);

        return $locale;
    }
}
