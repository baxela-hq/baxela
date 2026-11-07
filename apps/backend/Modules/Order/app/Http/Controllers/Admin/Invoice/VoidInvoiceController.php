<?php

namespace Modules\Order\Http\Controllers\Admin\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Actions\Admin\Invoice\VoidInvoiceAction;
use Modules\Order\Transformers\Admin\Invoice\OrderInvoiceResource;

class VoidInvoiceController extends Controller
{
    public function __construct(protected VoidInvoiceAction $action) {}

    public function __invoke(Request $request, int $id): JsonResource
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $invoice = $this->action->handle($id, $validated['reason']);

        return new OrderInvoiceResource($invoice);
    }
}
