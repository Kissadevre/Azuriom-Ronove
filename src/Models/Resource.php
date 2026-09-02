<?php

namespace Azuriom\Plugin\Ronove\Models;

use Azuriom\Models\Traits\HasTablePrefix;
use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    use HasTablePrefix;

    protected string $prefix = 'ronove_';

    protected $fillable = [
        'resource_type', 'resource_key',
    ];

    public function translations()
    {
        return $this->hasMany(Translation::class);
    }
}
