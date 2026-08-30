<?php

namespace Modules\Shipping\Http\Controllers\Admin\Method;

use App\Http\Controllers\Controller;
use Modules\Shipping\Actions\Admin\Method\UpdateMethodAction;
use Modules\Shipping\Http\Requests\Admin\Method\MethodRequest;
use Modules\Shipping\Transformers\Admin\Method\MethodResource;

class UpdateMethodController extends Controller
{
    public function __construct(protected UpdateMethodAction $action) {}

    public function __invoke(string $id, MethodRequest $request): MethodResource
    {
        return MethodResource::make($this->action->handle($id, $request->validated()));
    }
}
