<?php

namespace Modules\Shipping\Http\Controllers\Admin\Rate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shipping\Actions\Admin\Rate\ListRateAction;
use Modules\Shipping\Transformers\Admin\Rate\RateResource;

class ListRateController extends Controller
{
    public function __construct(protected ListRateAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return RateResource::collection($this->action->handle());
    }
}
