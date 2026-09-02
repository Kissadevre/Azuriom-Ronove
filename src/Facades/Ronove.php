<?php

namespace Azuriom\Plugin\Ronove\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Azuriom\Plugin\Ronove\Support\TranslationCoverageReport coverage(string $type, string $locale)
 * @method static void overlay(string $type, iterable $resources, ?string $locale = null)
 * @method static array<string, string|null> translatedValues(string $type, \Illuminate\Database\Eloquent\Model $resource, ?string $locale = null)
 * @method static string|null translate(string $type, \Illuminate\Database\Eloquent\Model $resource, string $field, ?string $locale = null)
 *
 * @see \Azuriom\Plugin\Ronove\RonoveManager
 */
class Ronove extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ronove';
    }
}
