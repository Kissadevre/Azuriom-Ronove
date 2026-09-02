<?php

namespace Azuriom\Plugin\Ronove\Controllers;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\Ronove\Events\LocaleChanged;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\UserPreference;
use Azuriom\Plugin\Ronove\Services\LanguageSwitcher;
use Azuriom\Plugin\Ronove\Services\LocaleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class LanguageController extends Controller
{
    public function index(LanguageSwitcher $switcher)
    {
        return view('ronove::index', [
            'languageOptions' => $switcher->options(),
        ]);
    }

    public function update(Request $request, LanguageSwitcher $switcher)
    {
        $available = $switcher->options()->pluck('code')->all();
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($available)],
        ]);
        $code = $validated['locale'];
        $isOriginal = $code === Locale::globalCode();

        $locale = $isOriginal
            ? null
            : Locale::query()->where('code', $code)->where('is_enabled', true)->translationTargets()->firstOrFail();

        $request->session()->put(LocaleManager::SESSION_KEY, $code);
        Cookie::queue(cookie(LocaleManager::COOKIE_NAME, $code, 60 * 24 * 365));

        if ($request->user() !== null) {
            if ($isOriginal) {
                UserPreference::query()->where('user_id', $request->user()->getAuthIdentifier())->delete();
            } else {
                UserPreference::query()->updateOrCreate(
                    ['user_id' => $request->user()->getAuthIdentifier()],
                    ['locale_id' => $locale->id],
                );
            }
        }

        LocaleChanged::dispatch(
            $code,
            $request->user() === null ? null : (int) $request->user()->getAuthIdentifier(),
        );

        return back()->with('success', trans('ronove::messages.updated'));
    }
}
