<?php

namespace Modules\Shipping\Http\Controllers\Admin\Rate;

use App\Http\Controllers\Controller;
use Modules\Shipping\Actions\Admin\Rate\CreateRateAction;
use Modules\Shipping\Http\Requests\Admin\Rate\RateRequest;
use Modules\Shipping\Transformers\Admin\Rate\RateResource;

class CreateRateController extends Controller
{
    public function __construct(protected CreateRateAction $action) {}

    public function __invoke(RateRequest $request): RateResource
    {
        return RateResource::make($this->action->handle($request->validated()));
    }
}
