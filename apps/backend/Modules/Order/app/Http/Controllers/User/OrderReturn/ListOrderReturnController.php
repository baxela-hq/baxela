<?php

namespace Modules\Order\Http\Controllers\User\OrderReturn;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Order\Actions\User\OrderReturn\ListOrderReturnsAction;
use Modules\Order\Transformers\User\OrderReturn\OrderReturnResource;

class ListOrderReturnController extends Controller
{
    public function __construct(protected ListOrderReturnsAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return OrderReturnResource::collection($this->action->handle());
    }
}
