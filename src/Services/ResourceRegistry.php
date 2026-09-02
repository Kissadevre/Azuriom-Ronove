<?php

namespace Azuriom\Plugin\Ronove\Services;

use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Support\TranslatableField;
use Azuriom\Plugin\Ronove\Support\TranslationIntegration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ResourceRegistry
{
    public const DEFAULT_INTEGRATION = 'other';

    /**
     * @var array<string, ResourceProvider>
     */
    private array $providers = [];

    /**
     * @var array<string, TranslationIntegration>
     */
    private array $integrations = [];

    /**
     * @var array<string, string>
     */
    private array $providerIntegrations = [];

    public function __construct()
    {
        $this->registerIntegration(new TranslationIntegration(
            self::DEFAULT_INTEGRATION,
            'ronove::admin.integrations.other',
            'bi bi-puzzle',
            order: 1000,
        ));
    }

    public function registerIntegration(TranslationIntegration $integration): void
    {
        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $integration->id) !== 1) {
            throw new InvalidArgumentException("Invalid Ronove integration [{$integration->id}].");
        }

        if (isset($this->integrations[$integration->id])) {
            throw new InvalidArgumentException("The Ronove integration [{$integration->id}] is already registered.");
        }

        $this->integrations[$integration->id] = $integration;
    }

    public function register(ResourceProvider $provider, ?string $integration = null): void
    {
        $type = $provider->type();
        $integration ??= self::DEFAULT_INTEGRATION;

        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $type) !== 1) {
            throw new InvalidArgumentException("Invalid Ronove resource type [{$type}].");
        }

        if (isset($this->providers[$type])) {
            throw new InvalidArgumentException("The Ronove resource type [{$type}] is already registered.");
        }

        if (! isset($this->integrations[$integration])) {
            throw new InvalidArgumentException("Unknown Ronove integration [{$integration}] for [{$type}].");
        }

        if (! is_a($provider->model(), Model::class, true)) {
            throw new InvalidArgumentException("The Ronove resource type [{$type}] must reference an Eloquent model.");
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
        $this->providerIntegrations[$type] = $integration;
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

    /**
     * @return Collection<string, TranslationIntegration>
     */
    public function integrations(): Collection
    {
        return collect($this->integrations)
            ->sortBy(fn (TranslationIntegration $integration) => [$integration->order, $integration->label()]);
    }

    public function integration(string $id): TranslationIntegration
    {
        return $this->integrations[$id]
            ?? throw new InvalidArgumentException("Unknown Ronove integration [{$id}].");
    }

    public function hasIntegration(string $id): bool
    {
        return isset($this->integrations[$id]);
    }

    public function integrationFor(string $type): TranslationIntegration
    {
        return $this->integration(
            $this->providerIntegrations[$type]
                ?? throw new InvalidArgumentException("Unknown Ronove resource type [{$type}].")
        );
    }

    /**
     * @return Collection<string, ResourceProvider>
     */
    public function forIntegration(string $id): Collection
    {
        $this->integration($id);

        return collect($this->providers)
            ->filter(fn (ResourceProvider $provider) => $this->providerIntegrations[$provider->type()] === $id);
    }
}
