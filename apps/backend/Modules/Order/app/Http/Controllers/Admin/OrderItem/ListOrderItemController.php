<?php

namespace Modules\Order\Http\Controllers\Admin\OrderItem;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Order\Actions\Admin\OrderItem\ListOrderItemAction;
use Modules\Order\Transformers\Admin\OrderItem\OrderItemResource;

class ListOrderItemController extends Controller
{
    public function __construct(protected ListOrderItemAction $action) {}

    public function __invoke(string $id): AnonymousResourceCollection
    {
        return OrderItemResource::collection($this->action->handle($id));
    }
}
