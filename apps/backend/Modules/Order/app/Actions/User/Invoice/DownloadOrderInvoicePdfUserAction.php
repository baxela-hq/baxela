<?php

namespace Modules\Order\Actions\User\Invoice;

use Illuminate\Support\Facades\Storage;
use Modules\Core\Utils\Auth;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\OrderInvoice;
use Modules\Order\Schemas\Document\DocumentPdfStatusEnum;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the customer's own invoice artifact — ready documents only,
 * and ownership of the parent order is enforced (another customer's
 * invoice is a 404, not a 403: document ids are not enumerable proof).
 */
class DownloadOrderInvoicePdfUserAction
{
    public function handle(int $invoiceId): StreamedResponse
    {
        $invoice = OrderInvoice::query()->findOrFail($invoiceId);

        if ((int) $invoice->order()->value('user_id') !== (int) Auth::id()) {
            throw OrderException::orderNotFound();
        }

        if ($invoice->pdf_status !== DocumentPdfStatusEnum::READY) {
            throw OrderException::invoicePdfNotReady();
        }

        return Storage::disk($invoice->pdf_disk)
            ->download($invoice->pdf_path, $invoice->number.'.pdf', [
                'Content-Type' => 'application/pdf',
            ]);
    }
}
