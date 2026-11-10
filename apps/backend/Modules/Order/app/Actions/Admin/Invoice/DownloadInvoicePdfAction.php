<?php

namespace Modules\Order\Actions\Admin\Invoice;

use Illuminate\Support\Facades\Storage;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\OrderInvoice;
use Modules\Order\Schemas\Document\DocumentPdfStatusEnum;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the stored artifact — ready documents only (D7): a pending,
 * generating or failed document must never reach a download, even
 * though its legal record exists.
 */
class DownloadInvoicePdfAction
{
    public function handle(int $id): StreamedResponse
    {
        $invoice = OrderInvoice::query()->findOrFail($id);

        if ($invoice->pdf_status !== DocumentPdfStatusEnum::READY) {
            throw OrderException::invoicePdfNotReady();
        }

        return Storage::disk($invoice->pdf_disk)
            ->download($invoice->pdf_path, $invoice->number.'.pdf', [
                'Content-Type' => 'application/pdf',
            ]);
    }
}
