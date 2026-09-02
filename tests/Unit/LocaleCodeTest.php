<?php

namespace Azuriom\Plugin\Ronove\Tests\Unit;

use Azuriom\Plugin\Ronove\Support\LocaleCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocaleCodeTest extends TestCase
{
    #[DataProvider('localeProvider')]
    public function test_it_normalizes_locale_codes(string $input, string $expected): void
    {
        $this->assertSame($expected, LocaleCode::normalize($input));
    }

    public static function localeProvider(): array
    {
        return [
            ['en', 'en'],
            ['es-ES', 'es_ES'],
            ['pt_br', 'pt_BR'],
            ['ZH-cn', 'zh_CN'],
        ];
    }
}
