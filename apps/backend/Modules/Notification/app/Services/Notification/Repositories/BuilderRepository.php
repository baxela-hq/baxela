<?php

namespace Modules\Notification\Services\Notification\Repositories;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Modules\Notification\Services\Notification\Contracts\BuilderRepositoryInterface;
use Modules\Notification\Services\Notification\Contracts\ChannelPayloadBuilderInterface;

class BuilderRepository implements BuilderRepositoryInterface
{
    private array $repositories;

    // Use Laravel's Container to resolve channel instances
    public function __construct(private Container $container, array $channelMap)
    {
        $this->repositories = $channelMap;
    }

    public function get(string $name): ChannelPayloadBuilderInterface
    {
        if (! isset($this->repositories[$name])) {
            throw new InvalidArgumentException("Repository '{$name}' not found.");
        }

        // Resolve the channel class using Laravel's container
        return $this->container->make($this->repositories[$name]);
    }
}
