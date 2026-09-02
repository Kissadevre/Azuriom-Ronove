<?php

namespace Azuriom\Plugin\Ronove\Support;

final readonly class ThemeTranslationManifest
{
    /**
     * @param  array<string, ThemeTranslationBlock>  $blocks
     */
    public function __construct(
        public string $theme,
        public string $integrationId,
        public string $label,
        public string $icon,
        public ?string $permission,
        public int $order,
        public string $settingName,
        public array $blocks,
    ) {}

    public function providerType(string $block): string
    {
        return $this->integrationId.'.'.$block;
    }
}
