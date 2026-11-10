<?php

namespace Modules\Order\Http\Controllers\User\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Order\Actions\User\Invoice\ListOrderInvoicesUserAction;

class ListOrderInvoiceUserController extends Controller
{
    public function __construct(protected ListOrderInvoicesUserAction $action) {}

    public function __invoke(string $code): JsonResponse
    {
        return response()->json([
            'data' => $this->action->handle($code),
        ]);
    }
}
