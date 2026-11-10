<?php

namespace Modules\Order\Actions\Admin\Invoice;

use Modules\Order\Documents\DocumentPdfService;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\OrderInvoice;
use Modules\Order\Schemas\Document\DocumentPdfStatusEnum;

/**
 * Admin-accessible retry of a FAILED generation only (D7 v2.2): the
 * retry reuses the same number and immutable snapshot. Regenerating a
 * ready artifact is ops-only artisan — deliberately not this endpoint.
 */
class RetryInvoicePdfAction
{
    public function __construct(protected DocumentPdfService $service) {}

    public function handle(int $id): OrderInvoice
    {
        $invoice = OrderInvoice::query()->findOrFail($id);

        if ($invoice->pdf_status !== DocumentPdfStatusEnum::GENERATION_FAILED) {
            throw OrderException::invoicePdfNotRetryable();
        }

        $this->service->generate($invoice);

        return $invoice->refresh();
    }
}
