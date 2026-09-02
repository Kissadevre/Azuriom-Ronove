<?php

namespace Azuriom\Plugin\Ronove\Support;

use InvalidArgumentException;

final class TranslatableField
{
    public const TEXT = 'text';

    public const TEXTAREA = 'textarea';

    public const MARKDOWN = 'markdown';

    public const RICH_TEXT = 'rich_text';

    private const TYPES = [self::TEXT, self::TEXTAREA, self::MARKDOWN, self::RICH_TEXT];

    public function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly ?int $maxLength = null,
    ) {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported Ronove field type [{$type}].");
        }

        if ($maxLength !== null && $maxLength < 1) {
            throw new InvalidArgumentException('A translatable field maximum length must be positive.');
        }
    }

    public static function text(string $label, ?int $maxLength = null): self
    {
        return new self(self::TEXT, $label, $maxLength);
    }

    public static function textarea(string $label, ?int $maxLength = null): self
    {
        return new self(self::TEXTAREA, $label, $maxLength);
    }

    public static function markdown(string $label): self
    {
        return new self(self::MARKDOWN, $label);
    }

    public static function richText(string $label): self
    {
        return new self(self::RICH_TEXT, $label);
    }
}
