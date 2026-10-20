<?php

namespace Modules\Order\Http\Controllers\User\OrderReturn;

use App\Http\Controllers\Controller;
use Modules\Order\Actions\User\OrderReturn\UpdateOwnOrderReturnAction;
use Modules\Order\Transformers\User\OrderReturn\OrderReturnResource;

class CancelOrderReturnController extends Controller
{
    public function __construct(protected UpdateOwnOrderReturnAction $action) {}

    public function __invoke(string $id): OrderReturnResource
    {
        return OrderReturnResource::make($this->action->cancel($id));
    }
}
