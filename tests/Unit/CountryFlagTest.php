<?php

namespace Azuriom\Plugin\Ronove\Tests\Unit;

use Azuriom\Plugin\Ronove\Support\CountryFlag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CountryFlagTest extends TestCase
{
    #[DataProvider('localeDefaults')]
    public function test_it_resolves_sensible_default_country_codes(string $locale, string $expected): void
    {
        $this->assertSame($expected, CountryFlag::defaultForLocale($locale));
    }

    public static function localeDefaults(): array
    {
        return [
            ['es_ES', 'ES'],
            ['pt_BR', 'BR'],
            ['en', 'GB'],
            ['uk', 'UA'],
        ];
    }

    public function test_it_builds_a_flag_without_accepting_invalid_codes(): void
    {
        $this->assertSame('🇲🇽', CountryFlag::emoji('mx'));
        $this->assertNull(CountryFlag::emoji('mex'));
        $this->assertNull(CountryFlag::normalize('1A'));
    }
}
