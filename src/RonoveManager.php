<?php

namespace Azuriom\Plugin\Ronove;

use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Services\TranslationResolver;
use Illuminate\Database\Eloquent\Model;

class RonoveManager
{
    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly TranslationResolver $translations,
    ) {}

    public function registerResourceType(ResourceProvider $provider): void
    {
        $this->registry->register($provider);
    }

    public function resources(): ResourceRegistry
    {
        return $this->registry;
    }

    public function translate(string $type, Model $resource, string $field, ?string $locale = null): ?string
    {
        return $this->translations->translate($type, $resource, $field, $locale);
    }

    /**
     * @return array<string, string|null>
     */
    public function translatedValues(string $type, Model $resource, ?string $locale = null): array
    {
        return $this->translations->values($type, $resource, $locale);
    }

    public function forget(string $type, Model $resource): void
    {
        $provider = $this->registry->get($type);

        Resource::query()
            ->where('resource_type', $type)
            ->where('resource_key', $provider->key($resource))
            ->delete();
    }
}
