<?php

namespace Azuriom\Plugin\Ronove\Contracts;

use Azuriom\Plugin\Ronove\Support\TranslatableField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface ResourceProvider
{
    public function type(): string;

    public function label(): string;

    public function permission(): ?string;

    /**
     * @return array<string, TranslatableField>
     */
    public function fields(): array;

    public function query(): Builder;

    public function find(string $key): ?Model;

    public function key(Model $resource): string;

    public function title(Model $resource): string;

    public function original(Model $resource, string $field): ?string;
}
