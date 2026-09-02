<?php

namespace Azuriom\Plugin\Ronove\Support;

final class TranslationPreviewField
{
    public const SELECTED = 'selected';

    public const FALLBACK = 'fallback';

    public const GLOBAL = 'global';

    public const ORIGINAL = 'original';

    public function __construct(
        public readonly ?string $original,
        public readonly ?string $value,
        public readonly string $source,
        public readonly ?string $sourceLocale = null,
    ) {}
}
