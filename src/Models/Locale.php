<?php

namespace Azuriom\Plugin\Ronove\Models;

use Azuriom\Models\Traits\HasTablePrefix;
use Illuminate\Database\Eloquent\Model;

class Locale extends Model
{
    use HasTablePrefix;

    protected string $prefix = 'ronove_';

    protected $fillable = [
        'code', 'name', 'native_name', 'is_enabled', 'position',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'position' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function translations()
    {
        return $this->hasMany(Translation::class);
    }
}
