<?php

namespace Azuriom\Plugin\Ronove\Models;

use Azuriom\Models\Traits\HasTablePrefix;
use Illuminate\Database\Eloquent\Model;

class GlossaryTerm extends Model
{
    use HasTablePrefix;

    public const GLOBAL_SCOPE = '__global__';

    protected string $prefix = 'ronove_';

    protected $fillable = [
        'scope', 'locale_id', 'source_text', 'source_key', 'translated_text', 'context',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $term) {
            $term->source_text = trim($term->source_text);
            $term->source_key = self::keyFor($term->source_text);
        });
    }

    public static function keyFor(string $source): string
    {
        return hash('sha256', mb_strtolower(trim($source)));
    }

    public function locale()
    {
        return $this->belongsTo(Locale::class);
    }
}
