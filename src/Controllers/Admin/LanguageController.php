<?php

namespace Azuriom\Plugin\Ronove\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Http\Controllers\InstallController;
use Azuriom\Models\ActionLog;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Support\CountryFlag;
use Azuriom\Plugin\Ronove\Support\LocaleCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LanguageController extends Controller
{
    public function index()
    {
        $globalLocale = Locale::globalCode();
        $configuredLocales = Locale::query()
            ->with('fallback')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->keyBy('code');

        $availableLocales = InstallController::getAvailableLocales()
            ->reject(fn (string $name, string $code) => LocaleCode::normalize($code) === $globalLocale);

        return view('ronove::admin.languages', [
            'availableLocales' => $availableLocales,
            'configuredLocales' => $configuredLocales,
            'enabledLocales' => $configuredLocales->filter(
                fn (Locale $locale) => $locale->is_enabled && $locale->isTranslationTarget()
            ),
            'globalLocale' => $globalLocale,
            'globalLocaleName' => InstallController::getAvailableLocales()->get($globalLocale, $globalLocale),
        ]);
    }

    public function update(Request $request)
    {
        $allAvailable = InstallController::getAvailableLocaleCodes()->all();
        $globalLocale = Locale::globalCode();
        $available = collect($allAvailable)
            ->map(LocaleCode::normalize(...))
            ->reject(fn (string $code) => $code === $globalLocale)
            ->values()
            ->all();
        $validated = $request->validate([
            'locales' => ['sometimes', 'array'],
            'locales.*' => ['required', 'string', 'distinct', Rule::in($available)],
            'flags' => ['sometimes', 'array'],
            'flags.*' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
        ]);
        $enabled = array_map(LocaleCode::normalize(...), $validated['locales'] ?? []);
        $flags = collect($validated['flags'] ?? [])->mapWithKeys(
            fn ($flag, $code) => [LocaleCode::normalize((string) $code) => CountryFlag::normalize($flag)]
        );
        $configured = Locale::query()->get()->keyBy('code');

        DB::transaction(function () use ($allAvailable, $configured, $enabled, $flags) {
            foreach ($allAvailable as $position => $code) {
                $normalized = LocaleCode::normalize($code);
                $nativeName = trans('messages.lang', [], $normalized);
                $existing = $configured->get($normalized);

                Locale::query()->updateOrCreate(
                    ['code' => $normalized],
                    [
                        'name' => $nativeName,
                        'native_name' => $nativeName,
                        'flag_code' => $flags->has($normalized)
                            ? $flags->get($normalized)
                            : ($existing?->flag_code ?? CountryFlag::defaultForLocale($normalized)),
                        'is_enabled' => in_array($normalized, $enabled, true),
                        'position' => $position,
                    ],
                );
            }

            $enabledIds = Locale::query()->where('is_enabled', true)->pluck('id');

            Locale::query()->where('is_enabled', false)->update(['fallback_locale_id' => null]);
            Locale::query()
                ->whereNotNull('fallback_locale_id')
                ->whereNotIn('fallback_locale_id', $enabledIds)
                ->update(['fallback_locale_id' => null]);
        });

        ActionLog::log('ronove.settings.updated');

        return to_route('ronove.admin.languages.index')
            ->with('success', trans('ronove::admin.languages.updated'));
    }

    public function updateFallbacks(Request $request)
    {
        $enabledLocales = Locale::query()
            ->where('is_enabled', true)
            ->translationTargets()
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $enabledCodes = $enabledLocales->pluck('code')->all();
        $validated = $request->validate([
            'fallbacks' => ['required', 'array'],
            'fallbacks.*' => ['nullable', 'string', Rule::in($enabledCodes)],
        ]);
        $mapping = $enabledLocales->mapWithKeys(function (Locale $locale) use ($validated) {
            $fallback = $validated['fallbacks'][$locale->code] ?? null;

            return [$locale->code => is_string($fallback) && $fallback !== '' ? $fallback : null];
        })->all();

        $this->ensureAcyclicFallbacks($mapping);
        $byCode = $enabledLocales->keyBy('code');

        DB::transaction(function () use ($enabledLocales, $byCode, $mapping) {
            foreach ($enabledLocales as $locale) {
                $fallback = $mapping[$locale->code];
                $locale->update([
                    'fallback_locale_id' => $fallback === null ? null : $byCode->get($fallback)->id,
                ]);
            }
        });

        ActionLog::log('ronove.settings.updated');

        return to_route('ronove.admin.languages.index')
            ->with('success', trans('ronove::admin.languages.fallbacks_updated'));
    }

    /**
     * @param  array<string, string|null>  $mapping
     */
    private function ensureAcyclicFallbacks(array $mapping): void
    {
        foreach (array_keys($mapping) as $start) {
            $visited = [];
            $current = $start;

            while ($current !== null) {
                if (isset($visited[$current])) {
                    throw ValidationException::withMessages([
                        'fallbacks.'.$start => trans('ronove::admin.languages.fallback_cycle'),
                    ]);
                }

                $visited[$current] = true;
                $current = $mapping[$current] ?? null;
            }
        }
    }
}
