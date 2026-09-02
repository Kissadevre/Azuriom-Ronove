<?php

namespace Azuriom\Plugin\Ronove\Services;

use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Support\TranslatableField;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ResourceRegistry
{
    /**
     * @var array<string, ResourceProvider>
     */
    private array $providers = [];

    public function register(ResourceProvider $provider): void
    {
        $type = $provider->type();

        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $type) !== 1) {
            throw new InvalidArgumentException("Invalid Ronove resource type [{$type}].");
        }

        if (isset($this->providers[$type])) {
            throw new InvalidArgumentException("The Ronove resource type [{$type}] is already registered.");
        }

        $fields = $provider->fields();

        if ($fields === []) {
            throw new InvalidArgumentException("The Ronove resource type [{$type}] must define at least one field.");
        }

        foreach ($fields as $name => $field) {
            if (! is_string($name) || preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
                throw new InvalidArgumentException("Invalid translatable field [{$name}] for [{$type}].");
            }

            if (! $field instanceof TranslatableField) {
                throw new InvalidArgumentException("The field [{$type}.{$name}] must be a TranslatableField.");
            }
        }

        $this->providers[$type] = $provider;
    }

    public function has(string $type): bool
    {
        return isset($this->providers[$type]);
    }

    public function get(string $type): ResourceProvider
    {
        return $this->providers[$type]
            ?? throw new InvalidArgumentException("Unknown Ronove resource type [{$type}].");
    }

    /**
     * @return Collection<string, ResourceProvider>
     */
    public function all(): Collection
    {
        return collect($this->providers);
    }
}
