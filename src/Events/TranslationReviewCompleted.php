<?php

namespace Azuriom\Plugin\Ronove\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TranslationReviewCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $resourceType,
        public readonly string $resourceKey,
        public readonly string $locale,
        public readonly string $decision,
        public readonly ?string $feedback,
        public readonly ?int $reviewerId,
    ) {}
}
