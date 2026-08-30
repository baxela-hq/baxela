<?php

namespace Modules\Shipping\Http\Controllers\Admin\Shipment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Shipping\Actions\Admin\Shipment\ShowShipmentAction;
use Modules\Shipping\Transformers\Admin\Shipment\ShipmentResource;

class ShowShipmentController extends Controller
{
    public function __construct(protected ShowShipmentAction $action) {}

    public function __invoke(string $id, Request $request): ShipmentResource
    {
        return ShipmentResource::make($this->action->handle($id));
    }
}
