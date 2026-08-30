<?php

namespace Modules\Shipping\Http\Controllers\Admin\Shipment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shipping\Actions\Admin\Shipment\ListShipmentAction;
use Modules\Shipping\Transformers\Admin\Shipment\ShipmentResource;

class ListShipmentController extends Controller
{
    public function __construct(protected ListShipmentAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return ShipmentResource::collection($this->action->handle());
    }
}
