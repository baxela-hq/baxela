<?php

namespace Modules\Shipping\Http\Controllers\Admin\Method;

use App\Http\Controllers\Controller;
use Modules\Shipping\Actions\Admin\Method\CreateMethodAction;
use Modules\Shipping\Http\Requests\Admin\Method\MethodRequest;
use Modules\Shipping\Transformers\Admin\Method\MethodResource;

class CreateMethodController extends Controller
{
    public function __construct(protected CreateMethodAction $action) {}

    public function __invoke(MethodRequest $request): MethodResource
    {
        return MethodResource::make($this->action->handle($request->validated()));
    }
}
