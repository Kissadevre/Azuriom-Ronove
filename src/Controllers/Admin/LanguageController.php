<?php

namespace Azuriom\Plugin\Ronove\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Http\Controllers\InstallController;
use Azuriom\Models\ActionLog;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Support\LocaleCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LanguageController extends Controller
{
    public function index()
    {
        $configuredLocales = Locale::query()
            ->with('fallback')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->keyBy('code');

        return view('ronove::admin.languages', [
            'availableLocales' => InstallController::getAvailableLocales(),
            'configuredLocales' => $configuredLocales,
            'enabledLocales' => $configuredLocales->filter(fn (Locale $locale) => $locale->is_enabled),
            'globalLocale' => LocaleCode::normalize(setting('locale', config('app.locale'))),
        ]);
    }

    public function update(Request $request)
    {
        $available = InstallController::getAvailableLocaleCodes()->all();
        $validated = $request->validate([
            'locales' => ['required', 'array', 'min:1'],
            'locales.*' => ['required', 'string', 'distinct', Rule::in($available)],
        ]);
        $enabled = array_map(LocaleCode::normalize(...), $validated['locales']);

        DB::transaction(function () use ($available, $enabled) {
            foreach ($available as $position => $code) {
                $normalized = LocaleCode::normalize($code);
                $nativeName = trans('messages.lang', [], $normalized);

                Locale::query()->updateOrCreate(
                    ['code' => $normalized],
                    [
                        'name' => $nativeName,
                        'native_name' => $nativeName,
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
