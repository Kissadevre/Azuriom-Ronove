<?php

namespace Azuriom\Plugin\Ronove\Providers\Resources;

use Azuriom\Models\Page;
use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Support\TranslatableField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PageResourceProvider implements ResourceProvider
{
    public function type(): string
    {
        return 'core.page';
    }

    public function model(): string
    {
        return Page::class;
    }

    public function label(): string
    {
        return trans('ronove::admin.resources.pages');
    }

    public function permission(): ?string
    {
        return 'admin.pages';
    }

    public function fields(): array
    {
        return [
            'title' => TranslatableField::text(trans('messages.fields.title'), 150),
            'content' => TranslatableField::richText(trans('messages.fields.content')),
        ];
    }

    public function query(): Builder
    {
        return Page::query()->latest('updated_at')->latest('id');
    }

    public function find(string $key): ?Model
    {
        return Page::query()->find($key);
    }

    public function key(Model $resource): string
    {
        return (string) $resource->getKey();
    }

    public function title(Model $resource): string
    {
        return (string) $resource->getRawOriginal('title');
    }

    public function original(Model $resource, string $field): ?string
    {
        $value = $resource->getRawOriginal($field);

        return $value === null ? null : (string) $value;
    }
}
