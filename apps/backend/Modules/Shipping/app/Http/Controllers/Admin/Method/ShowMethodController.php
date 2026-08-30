<?php

namespace Modules\Shipping\Http\Controllers\Admin\Method;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Shipping\Actions\Admin\Method\ShowMethodAction;
use Modules\Shipping\Transformers\Admin\Method\MethodResource;

class ShowMethodController extends Controller
{
    public function __construct(protected ShowMethodAction $action) {}

    public function __invoke(string $id, Request $request): MethodResource
    {
        return MethodResource::make($this->action->handle($id));
    }
}
