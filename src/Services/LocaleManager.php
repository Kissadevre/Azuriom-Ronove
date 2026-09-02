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
        return Locale::globalCode();
    }

    /**
     * @return Collection<int, Locale>
     */
    public function enabled(): Collection
    {
        return Locale::query()
            ->where('is_enabled', true)
            ->translationTargets()
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * Return the selected locale, its configured fallbacks, and Azuriom's global locale.
     *
     * @return array<int, string>
     */
    public function translationChain(string $locale): array
    {
        $selected = LocaleCode::normalize($locale);
        $global = $this->globalLocale();
        $chain = [$selected];
        $enabled = $this->enabled();
        $byId = $enabled->keyBy('id');
        $current = $enabled->firstWhere('code', $selected);
        $visited = $current === null ? [] : [$current->id => true];

        while ($current?->fallback_locale_id !== null) {
            $fallback = $byId->get($current->fallback_locale_id);

            if (! $fallback instanceof Locale || isset($visited[$fallback->id])) {
                break;
            }

            if ($selected !== $global && $fallback->code === $global) {
                break;
            }

            $visited[$fallback->id] = true;
            $chain[] = $fallback->code;
            $current = $fallback;
        }

        if (! in_array($global, $chain, true)) {
            $chain[] = $global;
        }

        return $chain;
    }

    public function resolve(Request $request): string
    {
        $enabled = $this->enabled();
        $global = $this->globalLocale();

        if ($enabled->isEmpty()) {
            return $global;
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

            if ($normalized === $global) {
                return $global;
            }

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

        return $global;
    }

    public function apply(Request $request): string
    {
        $locale = $this->resolve($request);
        $fallback = $this->globalLocale();
        $chain = $this->translationChain($locale);
        $translator = app('translator');

        app()->setLocale($locale);
        $translator->setFallback($fallback);
        $translator->determineLocalesUsing(
            fn (array $locales) => LocaleCode::normalize((string) ($locales[0] ?? $locale)) === $locale
                ? $chain
                : array_values(array_unique($locales))
        );
        Carbon::setLocale($locale);
        $request->attributes->set(self::SESSION_KEY, $locale);

        return $locale;
    }
}
