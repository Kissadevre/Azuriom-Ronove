<?php

namespace Azuriom\Plugin\Ronove\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface FilterableResourceProvider
{
    public function applySearch(Builder $query, string $search): Builder;

    /**
     * @param  Collection<int, string>  $keys
     */
    public function applyResourceKeys(Builder $query, Collection $keys): Builder;
}
