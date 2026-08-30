<?php

namespace Modules\Shipping\Http\Controllers\User\Shipment;

use App\Http\Controllers\Controller;
use Modules\Shipping\Actions\User\Shipment\ShowShipmentAction;
use Modules\Shipping\Transformers\User\Shipment\ShipmentResource;

class ShowShipmentController extends Controller
{
    public function __construct(protected ShowShipmentAction $action) {}

    public function __invoke(string $orderId): ShipmentResource
    {
        return ShipmentResource::make($this->action->handle($orderId));
    }
}
