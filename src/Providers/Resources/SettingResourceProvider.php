<?php

namespace Azuriom\Plugin\Ronove\Providers\Resources;

use Azuriom\Models\Setting;
use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class SettingResourceProvider implements ResourceProvider
{
    public function model(): string
    {
        return Setting::class;
    }

    public function permission(): ?string
    {
        return 'admin.settings';
    }

    public function query(): Builder
    {
        return Setting::query()->whereIn('name', array_keys($this->settings()))->orderBy('name');
    }

    public function find(string $key): ?Model
    {
        if (! array_key_exists($key, $this->settings())) {
            return null;
        }

        return Setting::query()->where('name', $key)->first();
    }

    public function key(Model $resource): string
    {
        return (string) $resource->getAttribute('name');
    }

    public function title(Model $resource): string
    {
        return trans($this->settings()[$this->key($resource)]);
    }

    public function original(Model $resource, string $field): ?string
    {
        $value = $resource->getAttribute('value');

        return $value === null ? null : (string) $value;
    }

    /**
     * @return array<string, string>
     */
    abstract public function settings(): array;
}
