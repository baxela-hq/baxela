<?php

namespace Modules\Shipping\Http\Controllers\Admin\Method;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shipping\Actions\Admin\Method\ListMethodAction;
use Modules\Shipping\Transformers\Admin\Method\MethodResource;

class ListMethodController extends Controller
{
    public function __construct(protected ListMethodAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return MethodResource::collection($this->action->handle());
    }
}
