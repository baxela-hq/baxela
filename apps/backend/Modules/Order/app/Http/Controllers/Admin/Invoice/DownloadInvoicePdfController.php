<?php

namespace Modules\Order\Http\Controllers\Admin\Invoice;

use App\Http\Controllers\Controller;
use Modules\Order\Actions\Admin\Invoice\DownloadInvoicePdfAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadInvoicePdfController extends Controller
{
    public function __construct(protected DownloadInvoicePdfAction $action) {}

    public function __invoke(int $id): StreamedResponse
    {
        return $this->action->handle($id);
    }
}
