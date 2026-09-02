<?php

namespace Azuriom\Plugin\Ronove\Middleware;

use Azuriom\Plugin\Ronove\Services\LocaleManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private readonly LocaleManager $locales)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->locales->apply($request);

        return $next($request);
    }
}
