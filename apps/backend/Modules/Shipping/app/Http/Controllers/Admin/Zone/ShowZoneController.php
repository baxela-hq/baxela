<?php

namespace Modules\Shipping\Http\Controllers\Admin\Zone;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Shipping\Actions\Admin\Zone\ShowZoneAction;
use Modules\Shipping\Transformers\Admin\Zone\ZoneResource;

class ShowZoneController extends Controller
{
    public function __construct(protected ShowZoneAction $action) {}

    public function __invoke(string $id, Request $request): ZoneResource
    {
        return ZoneResource::make($this->action->handle($id));
    }
}
