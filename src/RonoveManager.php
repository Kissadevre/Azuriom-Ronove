<?php

namespace Azuriom\Plugin\Ronove;

use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;

class RonoveManager
{
    public function __construct(private readonly ResourceRegistry $registry)
    {
    }

    public function registerResourceType(ResourceProvider $provider): void
    {
        $this->registry->register($provider);
    }

    public function resources(): ResourceRegistry
    {
        return $this->registry;
    }
}
