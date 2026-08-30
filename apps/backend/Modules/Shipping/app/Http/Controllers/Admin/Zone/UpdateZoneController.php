<?php

namespace Modules\Shipping\Http\Controllers\Admin\Zone;

use App\Http\Controllers\Controller;
use Modules\Shipping\Actions\Admin\Zone\UpdateZoneAction;
use Modules\Shipping\Http\Requests\Admin\Zone\ZoneRequest;
use Modules\Shipping\Transformers\Admin\Zone\ZoneResource;

class UpdateZoneController extends Controller
{
    public function __construct(protected UpdateZoneAction $action) {}

    public function __invoke(string $id, ZoneRequest $request): ZoneResource
    {
        return ZoneResource::make($this->action->handle($id, $request->validated()));
    }
}
