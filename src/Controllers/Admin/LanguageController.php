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

class LanguageController extends Controller
{
    public function index()
    {
        return view('ronove::admin.languages', [
            'availableLocales' => InstallController::getAvailableLocales(),
            'configuredLocales' => Locale::query()->get()->keyBy('code'),
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
        });

        ActionLog::log('ronove.settings.updated');

        return to_route('ronove.admin.languages.index')
            ->with('success', trans('ronove::admin.languages.updated'));
    }
}
