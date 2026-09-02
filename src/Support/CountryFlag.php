<?php

namespace Azuriom\Plugin\Ronove\Support;

final class CountryFlag
{
    private const DEFAULTS = [
        'ca' => 'ES',
        'cs' => 'CZ',
        'de' => 'DE',
        'en' => 'GB',
        'es' => 'ES',
        'fi' => 'FI',
        'fr' => 'FR',
        'hu' => 'HU',
        'id' => 'ID',
        'ko' => 'KR',
        'lt' => 'LT',
        'nl' => 'NL',
        'pl' => 'PL',
        'pt' => 'PT',
        'ru' => 'RU',
        'sv' => 'SE',
        'tr' => 'TR',
        'uk' => 'UA',
        'zh' => 'CN',
    ];

    public static function normalize(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return preg_match('/^[A-Z]{2}$/', $code) === 1 ? $code : null;
    }

    public static function defaultForLocale(string $locale): ?string
    {
        $normalized = LocaleCode::normalize($locale);
        $parts = explode('_', $normalized, 2);

        if (isset($parts[1]) && strlen($parts[1]) === 2) {
            return self::normalize($parts[1]);
        }

        return self::DEFAULTS[$parts[0]] ?? null;
    }

    public static function emoji(?string $code): ?string
    {
        $code = self::normalize($code);

        if ($code === null) {
            return null;
        }

        return mb_chr(127397 + ord($code[0])).mb_chr(127397 + ord($code[1]));
    }
}
