<?php

namespace Azuriom\Plugin\Ronove\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TranslationDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $resourceType,
        public readonly string $resourceKey,
        public readonly string $locale,
        public readonly string $previousStatus,
    ) {}
}
