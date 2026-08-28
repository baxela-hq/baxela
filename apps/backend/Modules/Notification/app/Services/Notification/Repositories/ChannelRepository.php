<?php

namespace Modules\Notification\Services\Notification\Repositories;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Modules\Notification\Services\Notification\Contracts\ChannelRepositoryInterface;
use Modules\Notification\Services\Notification\Contracts\NotificationChannelInterface;

class ChannelRepository implements ChannelRepositoryInterface
{
    private array $channels;

    // Use Laravel's Container to resolve channel instances
    public function __construct(private Container $container, array $channelMap)
    {
        $this->channels = $channelMap;
    }

    public function get(string $name): NotificationChannelInterface
    {
        if (! isset($this->channels[$name])) {
            throw new InvalidArgumentException("Channel '{$name}' not found.");
        }

        // Resolve the channel class using Laravel's container
        return $this->container->make($this->channels[$name]);
    }
}
