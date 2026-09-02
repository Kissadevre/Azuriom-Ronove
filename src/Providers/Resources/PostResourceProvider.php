<?php

namespace Azuriom\Plugin\Ronove\Providers\Resources;

use Azuriom\Models\Post;
use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Support\TranslatableField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PostResourceProvider implements ResourceProvider
{
    public function type(): string
    {
        return 'core.post';
    }

    public function model(): string
    {
        return Post::class;
    }

    public function label(): string
    {
        return trans('ronove::admin.resources.posts');
    }

    public function permission(): ?string
    {
        return null;
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
        return Post::query()->latest('published_at')->latest('id');
    }

    public function find(string $key): ?Model
    {
        return Post::query()->find($key);
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
