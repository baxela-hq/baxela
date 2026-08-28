<?php

namespace Modules\Notification\Services\Notification\Repositories;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Modules\Notification\Services\Notification\Contracts\TemplateEngineInterface;
use Modules\Notification\Services\Notification\Contracts\TemplateRepositoryInterface;

class TemplateRepository implements TemplateRepositoryInterface
{
    private array $repositories;

    // Use Laravel's Container to resolve channel instances
    public function __construct(private Container $container, array $channelMap)
    {
        $this->repositories = $channelMap;
    }

    public function get(string $name): TemplateEngineInterface
    {
        if (! isset($this->repositories[$name])) {
            throw new InvalidArgumentException("Repository '{$name}' not found.");
        }

        // Resolve the channel class using Laravel's container
        return $this->container->make($this->repositories[$name]);
    }
}
