<?php

namespace Modules\Order\Http\Controllers\Admin\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Actions\Admin\Invoice\ShowOrderInvoiceAction;
use Modules\Order\Transformers\Admin\Invoice\OrderInvoiceResource;

class ShowOrderInvoiceController extends Controller
{
    public function __construct(protected ShowOrderInvoiceAction $action) {}

    public function __invoke(Request $request, int $id): JsonResource
    {
        return new OrderInvoiceResource($this->action->handle($id));
    }
}
