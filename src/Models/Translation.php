<?php

namespace Azuriom\Plugin\Ronove\Models;

use Azuriom\Models\Traits\HasTablePrefix;
use Azuriom\Models\User;
use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    use HasTablePrefix;

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const REVIEW_DRAFT = 'draft';

    public const REVIEW_PENDING = 'pending';

    public const REVIEW_CHANGES_REQUESTED = 'changes_requested';

    public const REVIEW_APPROVED = 'approved';

    public const REVIEW_STATUSES = [
        self::REVIEW_DRAFT,
        self::REVIEW_PENDING,
        self::REVIEW_CHANGES_REQUESTED,
        self::REVIEW_APPROVED,
    ];

    protected string $prefix = 'ronove_';

    protected $fillable = [
        'resource_id', 'locale_id', 'status', 'review_status', 'values', 'source_hash',
        'published_values', 'published_source_hash', 'reviewed_by', 'reviewed_at',
        'review_feedback',
    ];

    protected $casts = [
        'values' => 'array',
        'published_values' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    public function locale()
    {
        return $this->belongsTo(Locale::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function revisions()
    {
        return $this->hasMany(TranslationRevision::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    /**
     * @return array<string, string>|null
     */
    public function publicValues(): ?array
    {
        if (is_array($this->published_values)) {
            return $this->published_values;
        }

        return $this->isPublished() && is_array($this->values)
            ? $this->values
            : null;
    }

    public function hasPublishedVersion(): bool
    {
        return $this->publicValues() !== null;
    }
}
