<?php

namespace Modules\Order\Http\Controllers\Admin\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Order\Actions\Admin\Invoice\IssueInvoiceAction;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;
use Modules\Order\Transformers\Admin\Invoice\OrderInvoiceResource;
use Symfony\Component\HttpFoundation\Response;

class IssueInvoiceController extends Controller
{
    public function __construct(protected IssueInvoiceAction $action) {}

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $invoice = $this->action->handle($id);

        return (new OrderInvoiceResource($invoice->load(OrderInvoiceSchema::RES_ITEMS)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
