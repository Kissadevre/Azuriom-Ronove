<?php

namespace Azuriom\Plugin\Ronove\Models;

use Azuriom\Models\Traits\HasTablePrefix;
use Azuriom\Plugin\Ronove\Support\LocaleCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Locale extends Model
{
    use HasTablePrefix;

    protected string $prefix = 'ronove_';

    protected $fillable = [
        'code', 'name', 'native_name', 'flag_code', 'is_enabled', 'position', 'fallback_locale_id',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'position' => 'integer',
        'fallback_locale_id' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public static function globalCode(): string
    {
        return LocaleCode::normalize(setting('locale', config('app.locale', 'en')));
    }

    public function scopeTranslationTargets(Builder $query): Builder
    {
        return $query->where('code', '!=', self::globalCode());
    }

    public function isTranslationTarget(): bool
    {
        return $this->code !== self::globalCode();
    }

    public function translations()
    {
        return $this->hasMany(Translation::class);
    }

    public function fallback()
    {
        return $this->belongsTo(self::class, 'fallback_locale_id');
    }
}
