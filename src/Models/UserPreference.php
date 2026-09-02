<?php

namespace Azuriom\Plugin\Ronove\Models;

use Azuriom\Models\Traits\HasTablePrefix;
use Azuriom\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    use HasTablePrefix;

    protected string $prefix = 'ronove_';

    protected $fillable = [
        'user_id', 'locale_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function locale()
    {
        return $this->belongsTo(Locale::class);
    }
}
