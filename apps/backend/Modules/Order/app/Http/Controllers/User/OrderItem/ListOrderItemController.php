<?php

namespace Modules\Order\Http\Controllers\User\OrderItem;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Order\Actions\User\OrderItem\ListOrderItemAction;
use Modules\Order\Transformers\User\OrderItem\OrderItemResource;

class ListOrderItemController extends Controller
{
    public function __construct(protected ListOrderItemAction $action) {}

    public function __invoke(string $code, Request $request): AnonymousResourceCollection
    {
        return OrderItemResource::collection($this->action->handle($code));
    }
}
