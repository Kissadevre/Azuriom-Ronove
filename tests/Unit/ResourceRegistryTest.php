<?php

namespace Azuriom\Plugin\Ronove\Tests\Unit;

use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Support\TranslatableField;
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

    private function provider(string $type): ResourceProvider
    {
        return new class($type) implements ResourceProvider
        {
            public function __construct(private readonly string $resourceType)
            {
            }

            public function type(): string
            {
                return $this->resourceType;
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
