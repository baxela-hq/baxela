<?php

namespace Modules\Order\Http\Controllers\Admin\OrderReturn;

use App\Http\Controllers\Controller;
use Modules\Order\Actions\Admin\OrderReturn\ShowOrderReturnAction;
use Modules\Order\Transformers\Admin\OrderReturn\OrderReturnResource;

class ShowOrderReturnController extends Controller
{
    public function __construct(protected ShowOrderReturnAction $action) {}

    public function __invoke(string $id): OrderReturnResource
    {
        return OrderReturnResource::make($this->action->handle($id));
    }
}
