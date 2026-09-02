<?php

namespace Azuriom\Plugin\Ronove\Tests\Unit;

use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Support\TranslatableField;
use Azuriom\Plugin\Ronove\Support\TranslationIntegration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ResourceRegistryTest extends TestCase
{
    public function test_it_registers_a_valid_resource_provider(): void
    {
        $registry = new ResourceRegistry;
        $provider = $this->provider('example.article');

        $registry->register($provider);

        $this->assertTrue($registry->has('example.article'));
        $this->assertSame($provider, $registry->get('example.article'));
    }

    public function test_it_rejects_duplicate_resource_types(): void
    {
        $registry = new ResourceRegistry;
        $registry->register($this->provider('example.article'));

        $this->expectException(InvalidArgumentException::class);

        $registry->register($this->provider('example.article'));
    }

    public function test_it_groups_providers_by_registered_integration(): void
    {
        $registry = new ResourceRegistry;
        $integration = new TranslationIntegration('example', 'Example', 'bi bi-box', 'example.admin', 10);
        $provider = $this->provider('example.article');

        $registry->registerIntegration($integration);
        $registry->register($provider, 'example');

        $this->assertSame($integration, $registry->integration('example'));
        $this->assertSame($integration, $registry->integrationFor('example.article'));
        $this->assertSame($provider, $registry->forIntegration('example')->get('example.article'));
    }

    public function test_providers_without_an_explicit_integration_remain_compatible(): void
    {
        $registry = new ResourceRegistry;
        $registry->register($this->provider('legacy.article'));

        $this->assertSame(ResourceRegistry::DEFAULT_INTEGRATION, $registry->integrationFor('legacy.article')->id);
    }

    public function test_it_rejects_providers_for_unknown_integrations(): void
    {
        $registry = new ResourceRegistry;

        $this->expectException(InvalidArgumentException::class);

        $registry->register($this->provider('example.article'), 'missing');
    }

    private function provider(string $type): ResourceProvider
    {
        return new class($type) implements ResourceProvider
        {
            public function __construct(private readonly string $resourceType) {}

            public function type(): string
            {
                return $this->resourceType;
            }

            public function model(): string
            {
                return Model::class;
            }

            public function label(): string
            {
                return 'Articles';
            }

            public function permission(): ?string
            {
                return null;
            }

            public function fields(): array
            {
                return ['title' => TranslatableField::text('Title', 150)];
            }

            public function query(): Builder
            {
                throw new \LogicException('Not needed by this test.');
            }

            public function find(string $key): ?Model
            {
                return null;
            }

            public function key(Model $resource): string
            {
                return (string) $resource->getKey();
            }

            public function title(Model $resource): string
            {
                return (string) $resource->getAttribute('title');
            }

            public function original(Model $resource, string $field): ?string
            {
                return $resource->getAttribute($field);
            }
        };
    }
}
