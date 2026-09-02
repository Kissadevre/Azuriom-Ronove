<?php

namespace Azuriom\Plugin\Ronove\Controllers;

use Azuriom\Http\Controllers\Controller;
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

    public function update(Request $request)
    {
        $validated = $request->validate([
            'locale' => [
                'required',
                'string',
                Rule::exists('ronove_locales', 'code')->where('is_enabled', true),
            ],
        ]);

        $locale = Locale::query()->where('code', $validated['locale'])->firstOrFail();

        $request->session()->put(LocaleManager::SESSION_KEY, $locale->code);
        Cookie::queue(cookie(LocaleManager::COOKIE_NAME, $locale->code, 60 * 24 * 365));

        if ($request->user() !== null) {
            UserPreference::query()->updateOrCreate(
                ['user_id' => $request->user()->getAuthIdentifier()],
                ['locale_id' => $locale->id],
            );
        }

        return back()->with('success', trans('ronove::messages.updated'));
    }
}
