<?php

namespace Modules\Shipping\Actions\Admin\Zone;

use Illuminate\Database\Eloquent\Model;
use Modules\Shipping\Models\Zone;
use Modules\Shipping\Schemas\Zone\ZoneSchema;

class ShowZoneAction
{
    public function __construct(protected Zone $model) {}

    public function handle(string $id): Model
    {
        return $this->model->query()->with(ZoneSchema::RES_COUNTRIES)->findOrFail($id);
    }
}
