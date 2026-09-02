<?php

namespace Azuriom\Plugin\Ronove\Providers\Resources;

use Azuriom\Models\Setting;
use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Support\ThemeTranslationBlock;
use Azuriom\Plugin\Ronove\Support\ThemeTranslationManifest;
use Azuriom\Plugin\Ronove\Support\TranslatableField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ThemeSettingResourceProvider implements ResourceProvider
{
    public function __construct(
        private readonly ThemeTranslationManifest $manifest,
        private readonly ThemeTranslationBlock $block,
    ) {}

    public function type(): string
    {
        return $this->manifest->providerType($this->block->id);
    }

    public function model(): string
    {
        return Setting::class;
    }

    public function label(): string
    {
        return trans($this->block->label);
    }

    public function permission(): ?string
    {
        return $this->manifest->permission;
    }

    /**
     * @return array<string, TranslatableField>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->block->fields as $field) {
            $fields[$field->id] = $field->definition();
        }

        return $fields;
    }

    public function query(): Builder
    {
        return Setting::query()->where('name', $this->manifest->settingName);
    }

    public function find(string $key): ?Model
    {
        if ($key !== $this->manifest->settingName) {
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
        return $this->label();
    }

    public function original(Model $resource, string $field): ?string
    {
        $definition = $this->block->fields[$field] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown translatable theme field [{$this->type()}.{$field}].");
        }

        $config = $resource->getAttribute('value');
        $value = is_array($config) ? data_get($config, $definition->path) : null;

        return is_scalar($value) ? (string) $value : null;
    }

    public function path(string $field): string
    {
        $definition = $this->block->fields[$field] ?? null;

        return $definition?->path
            ?? throw new InvalidArgumentException("Unknown translatable theme field [{$this->type()}.{$field}].");
    }
}
