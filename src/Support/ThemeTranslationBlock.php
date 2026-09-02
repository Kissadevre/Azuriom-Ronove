<?php

namespace Azuriom\Plugin\Ronove\Support;

final readonly class ThemeTranslationBlock
{
    /**
     * @param  array<string, ThemeTranslationField>  $fields
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $fields,
    ) {}
}
