<?php

namespace Azuriom\Plugin\Ronove\Models;

use Azuriom\Models\Traits\HasTablePrefix;
use Azuriom\Models\User;
use Illuminate\Database\Eloquent\Model;

class TranslationRevision extends Model
{
    use HasTablePrefix;

    public const SAVED = 'saved';

    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const CHANGES_REQUESTED = 'changes_requested';

    public const RESTORED = 'restored';

    protected string $prefix = 'ronove_';

    protected $fillable = [
        'translation_id', 'user_id', 'action', 'status', 'review_status',
        'values', 'source_hash', 'feedback',
    ];

    protected $casts = [
        'values' => 'array',
    ];

    public function translation()
    {
        return $this->belongsTo(Translation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
