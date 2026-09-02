<?php

namespace Azuriom\Plugin\Ronove\Support;

final class LocaleCode
{
    public static function normalize(?string $locale): string
    {
        $parts = explode('_', str_replace('-', '_', trim((string) $locale)), 2);
        $language = strtolower($parts[0] ?? '');

        if ($language === '') {
            return 'en';
        }

        return isset($parts[1]) && $parts[1] !== ''
            ? $language.'_'.strtoupper($parts[1])
            : $language;
    }

    public static function language(string $locale): string
    {
        return explode('_', self::normalize($locale), 2)[0];
    }

    public static function html(string $locale): string
    {
        return str_replace('_', '-', self::normalize($locale));
    }
}
