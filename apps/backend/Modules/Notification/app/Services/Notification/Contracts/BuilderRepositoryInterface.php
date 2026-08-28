<?php

namespace Modules\Notification\Services\Notification\Contracts;

interface BuilderRepositoryInterface
{
    public function get(string $name): ChannelPayloadBuilderInterface;
}
