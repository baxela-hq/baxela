<?php

namespace Modules\Shipping\Http\Controllers\Admin\Shipment;

use App\Http\Controllers\Controller;
use Modules\Shipping\Actions\Admin\Shipment\UpdateShipmentAction;
use Modules\Shipping\Http\Requests\Admin\Shipment\ShipmentRequest;
use Modules\Shipping\Transformers\Admin\Shipment\ShipmentResource;

class UpdateShipmentController extends Controller
{
    public function __construct(protected UpdateShipmentAction $action) {}

    public function __invoke(string $id, ShipmentRequest $request): ShipmentResource
    {
        return ShipmentResource::make($this->action->handle($id, $request->validated()));
    }
}
