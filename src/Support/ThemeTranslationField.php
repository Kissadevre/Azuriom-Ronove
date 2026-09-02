<?php

namespace Azuriom\Plugin\Ronove\Support;

final readonly class ThemeTranslationField
{
    public function __construct(
        public string $id,
        public string $path,
        public string $label,
        public array $labelParameters,
        public string $type,
        public ?int $maxLength = null,
    ) {}

    public function definition(): TranslatableField
    {
        $label = trans($this->label, $this->labelParameters);

        return match ($this->type) {
            TranslatableField::TEXT => TranslatableField::text($label, $this->maxLength),
            TranslatableField::TEXTAREA => TranslatableField::textarea($label, $this->maxLength),
            TranslatableField::MARKDOWN => TranslatableField::markdown($label),
            TranslatableField::RICH_TEXT => TranslatableField::richText($label),
        };
    }
}
