<?php

namespace Azuriom\Plugin\Ronove\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TranslationSaved
{
    use Dispatchable;

    /**
     * @param  array<string, string>  $values
     */
    public function __construct(
        public readonly string $resourceType,
        public readonly string $resourceKey,
        public readonly string $locale,
        public readonly string $status,
        public readonly array $values,
        public readonly ?string $previousStatus,
    ) {}
}
