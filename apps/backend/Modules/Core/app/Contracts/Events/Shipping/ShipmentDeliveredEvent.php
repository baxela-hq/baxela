<?php

namespace Modules\Core\Contracts\Events\Shipping;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class ShipmentDeliveredEvent extends AbstractBaseEvent
{
    public int $id;

    public int $order_id;

    public string $status;

    public ?string $carrier_name;

    public ?string $tracking_number;

    public ?string $tracking_url;

    public ?string $shipped_at;

    public ?string $delivered_at;
}
