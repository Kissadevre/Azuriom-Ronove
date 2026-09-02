<?php

namespace Azuriom\Plugin\Ronove\Models;

use Azuriom\Models\Traits\HasTablePrefix;
use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    use HasTablePrefix;

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    protected string $prefix = 'ronove_';

    protected $fillable = [
        'resource_id', 'locale_id', 'status', 'values', 'source_hash',
    ];

    protected $casts = [
        'values' => 'array',
    ];

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    public function locale()
    {
        return $this->belongsTo(Locale::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }
}
