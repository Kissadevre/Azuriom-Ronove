<?php

namespace Azuriom\Plugin\Ronove\Services;

use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Models\TranslationRevision;

class TranslationRevisionRecorder
{
    public function record(
        Translation $translation,
        string $action,
        ?int $userId,
        ?string $feedback = null,
    ): TranslationRevision {
        return $translation->revisions()->create([
            'user_id' => $userId,
            'action' => $action,
            'status' => $translation->status,
            'review_status' => $translation->review_status,
            'values' => $translation->values,
            'source_hash' => $translation->source_hash,
            'feedback' => $feedback,
        ]);
    }
}
