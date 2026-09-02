<?php

namespace Azuriom\Plugin\Ronove\Middleware;

use Azuriom\Plugin\Ronove\Services\LocaleManager;
use Azuriom\Plugin\Ronove\Services\LocalizedSettings;
use Azuriom\Plugin\Ronove\Services\LocalizedThemeConfiguration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(
        private readonly LocaleManager $locales,
        private readonly LocalizedSettings $settings,
        private readonly LocalizedThemeConfiguration $theme,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->locales->apply($request);
        $this->settings->apply($request);
        $this->theme->apply($request);

        return $next($request);
    }
}
