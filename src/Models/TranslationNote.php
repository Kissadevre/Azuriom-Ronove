<?php

namespace Azuriom\Plugin\Ronove\Models;

use Azuriom\Models\Traits\HasTablePrefix;
use Illuminate\Database\Eloquent\Model;

class TranslationNote extends Model
{
    use HasTablePrefix;

    protected string $prefix = 'ronove_';

    protected $fillable = [
        'resource_id', 'locale_id', 'note',
    ];

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    public function locale()
    {
        return $this->belongsTo(Locale::class);
    }
}
