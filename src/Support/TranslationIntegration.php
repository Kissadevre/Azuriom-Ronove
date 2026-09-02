<?php

namespace Azuriom\Plugin\Ronove\Support;

final class TranslationIntegration
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $icon = 'bi bi-puzzle',
        public readonly ?string $permission = null,
        public readonly int $order = 100,
    ) {}

    public function label(): string
    {
        return trans($this->name);
    }
}
