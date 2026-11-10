<?php

namespace Modules\Order\Http\Controllers\Admin\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Actions\Admin\Invoice\RetryInvoicePdfAction;
use Modules\Order\Transformers\Admin\Invoice\OrderInvoiceResource;

class RetryInvoicePdfController extends Controller
{
    public function __construct(protected RetryInvoicePdfAction $action) {}

    public function __invoke(int $id): JsonResource
    {
        return new OrderInvoiceResource($this->action->handle($id));
    }
}
