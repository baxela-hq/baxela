<?php

namespace Modules\Shipping\Http\Controllers\Admin\Rate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Shipping\Actions\Admin\Rate\ShowRateAction;
use Modules\Shipping\Transformers\Admin\Rate\RateResource;

class ShowRateController extends Controller
{
    public function __construct(protected ShowRateAction $action) {}

    public function __invoke(string $id, Request $request): RateResource
    {
        return RateResource::make($this->action->handle($id));
    }
}
