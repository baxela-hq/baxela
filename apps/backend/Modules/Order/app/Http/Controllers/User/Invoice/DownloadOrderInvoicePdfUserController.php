<?php

namespace Modules\Order\Http\Controllers\User\Invoice;

use App\Http\Controllers\Controller;
use Modules\Order\Actions\User\Invoice\DownloadOrderInvoicePdfUserAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadOrderInvoicePdfUserController extends Controller
{
    public function __construct(protected DownloadOrderInvoicePdfUserAction $action) {}

    public function __invoke(int $id): StreamedResponse
    {
        return $this->action->handle($id);
    }
}
