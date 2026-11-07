<?php

namespace Modules\Order\Http\Controllers\Admin\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Order\Actions\Admin\Invoice\ListOrderInvoicesAction;
use Modules\Order\Transformers\Admin\Invoice\OrderInvoiceResource;

class ListOrderInvoiceController extends Controller
{
    public function __construct(protected ListOrderInvoicesAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return OrderInvoiceResource::collection($this->action->handle());
    }
}
