<?php

namespace Azuriom\Plugin\Ronove\Events;

use Illuminate\Foundation\Events\Dispatchable;

class LocaleChanged
{
    use Dispatchable;

    public function __construct(
        public readonly string $locale,
        public readonly ?int $userId,
    ) {}
}
