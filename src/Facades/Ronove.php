<?php

namespace Azuriom\Plugin\Ronove\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Azuriom\Plugin\Ronove\Support\TranslationCoverageReport coverage(string $type, string $locale)
 * @method static \Azuriom\Plugin\Ronove\Support\LocaleOption|null currentLanguage()
 * @method static void forget(string $type, \Illuminate\Database\Eloquent\Model $resource)
 * @method static \Illuminate\Support\Collection<int, \Azuriom\Plugin\Ronove\Support\LocaleOption> languageOptions()
 * @method static string languageUpdateUrl()
 * @method static void overlay(string $type, iterable $resources, ?string $locale = null)
 * @method static bool publicLanguagePageEnabled()
 * @method static void registerIntegration(string $id, string $name, string $icon = 'bi bi-puzzle', ?string $permission = null, int $order = 100)
 * @method static void registerResourceType(\Azuriom\Plugin\Ronove\Contracts\ResourceProvider $provider, ?string $integration = null)
 * @method static \Azuriom\Plugin\Ronove\Services\ResourceRegistry resources()
 * @method static bool reviewWorkflowEnabled()
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
