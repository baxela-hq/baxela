<?php

namespace Modules\Shipping\Http\Controllers\Admin\Zone;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shipping\Actions\Admin\Zone\ListZoneAction;
use Modules\Shipping\Transformers\Admin\Zone\ZoneResource;

class ListZoneController extends Controller
{
    public function __construct(protected ListZoneAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return ZoneResource::collection($this->action->handle());
    }
}
