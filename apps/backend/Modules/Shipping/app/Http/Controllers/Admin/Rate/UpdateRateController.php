<?php

namespace Modules\Shipping\Http\Controllers\Admin\Rate;

use App\Http\Controllers\Controller;
use Modules\Shipping\Actions\Admin\Rate\UpdateRateAction;
use Modules\Shipping\Http\Requests\Admin\Rate\RateRequest;
use Modules\Shipping\Transformers\Admin\Rate\RateResource;

class UpdateRateController extends Controller
{
    public function __construct(protected UpdateRateAction $action) {}

    public function __invoke(string $id, RateRequest $request): RateResource
    {
        return RateResource::make($this->action->handle($id, $request->validated()));
    }
}
