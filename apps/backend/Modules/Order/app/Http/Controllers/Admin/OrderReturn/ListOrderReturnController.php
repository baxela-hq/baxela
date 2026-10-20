<?php

namespace Modules\Order\Http\Controllers\Admin\OrderReturn;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Order\Actions\Admin\OrderReturn\ListOrderReturnAction;
use Modules\Order\Transformers\Admin\OrderReturn\OrderReturnResource;

class ListOrderReturnController extends Controller
{
    public function __construct(protected ListOrderReturnAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return OrderReturnResource::collection($this->action->handle());
    }
}
