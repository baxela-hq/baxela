<?php

namespace Modules\Shipping\Http\Controllers\Admin\Shipment;

use App\Http\Controllers\Controller;
use Modules\Shipping\Actions\Admin\Shipment\CreateShipmentAction;
use Modules\Shipping\Http\Requests\Admin\Shipment\ShipmentRequest;
use Modules\Shipping\Transformers\Admin\Shipment\ShipmentResource;

class CreateShipmentController extends Controller
{
    public function __construct(protected CreateShipmentAction $action) {}

    public function __invoke(ShipmentRequest $request): ShipmentResource
    {
        return ShipmentResource::make($this->action->handle($request->validated()));
    }
}
