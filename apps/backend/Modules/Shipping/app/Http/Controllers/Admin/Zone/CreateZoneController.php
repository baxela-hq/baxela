<?php

namespace Modules\Shipping\Http\Controllers\Admin\Zone;

use App\Http\Controllers\Controller;
use Modules\Shipping\Actions\Admin\Zone\CreateZoneAction;
use Modules\Shipping\Http\Requests\Admin\Zone\ZoneRequest;
use Modules\Shipping\Transformers\Admin\Zone\ZoneResource;

class CreateZoneController extends Controller
{
    public function __construct(protected CreateZoneAction $action) {}

    public function __invoke(ZoneRequest $request): ZoneResource
    {
        return ZoneResource::make($this->action->handle($request->validated()));
    }
}
