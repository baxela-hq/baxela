<?php

namespace Modules\Shipping\Actions\Admin\Shipment;

use Illuminate\Database\Eloquent\Model;
use Modules\Shipping\Models\Shipment;

class ShowShipmentAction
{
    public function __construct(protected Shipment $model) {}

    public function handle(string $id): Model
    {
        return $this->model->query()->findOrFail($id);
    }
}
