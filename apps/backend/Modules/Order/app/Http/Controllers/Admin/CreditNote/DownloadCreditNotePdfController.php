<?php

namespace Modules\Order\Http\Controllers\Admin\CreditNote;

use App\Http\Controllers\Controller;
use Modules\Order\Actions\Admin\CreditNote\DownloadCreditNotePdfAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadCreditNotePdfController extends Controller
{
    public function __construct(protected DownloadCreditNotePdfAction $action) {}

    public function __invoke(int $id): StreamedResponse
    {
        return $this->action->handle($id);
    }
}
