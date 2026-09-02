<?php

namespace Azuriom\Plugin\Ronove\Providers\Resources;

use Azuriom\Models\Setting;
use Azuriom\Plugin\Ronove\Contracts\FilterableResourceProvider;
use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class SettingResourceProvider implements FilterableResourceProvider, ResourceProvider
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

    public function applySearch(Builder $query, string $search): Builder
    {
        $matchingNames = collect($this->settings())
            ->filter(fn (string $label) => str_contains(mb_strtolower(trans($label)), mb_strtolower($search)))
            ->keys();

        return $query->where(function (Builder $query) use ($matchingNames, $search) {
            $query->where('value', 'like', '%'.$search.'%');

            if ($matchingNames->isNotEmpty()) {
                $query->orWhereIn('name', $matchingNames);
            }
        });
    }

    public function applyResourceKeys(Builder $query, Collection $keys): Builder
    {
        return $query->whereIn('name', $keys);
    }

    /**
     * @return array<string, string>
     */
    abstract public function settings(): array;
}
