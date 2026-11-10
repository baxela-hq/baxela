<?php

namespace Modules\Order\Actions\Admin\CreditNote;

use Illuminate\Support\Facades\Storage;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\OrderCreditNote;
use Modules\Order\Schemas\Document\DocumentPdfStatusEnum;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadCreditNotePdfAction
{
    public function handle(int $id): StreamedResponse
    {
        $creditNote = OrderCreditNote::query()->findOrFail($id);

        if ($creditNote->pdf_status !== DocumentPdfStatusEnum::READY) {
            throw OrderException::invoicePdfNotReady();
        }

        return Storage::disk($creditNote->pdf_disk)
            ->download($creditNote->pdf_path, $creditNote->number.'.pdf', [
                'Content-Type' => 'application/pdf',
            ]);
    }
}
