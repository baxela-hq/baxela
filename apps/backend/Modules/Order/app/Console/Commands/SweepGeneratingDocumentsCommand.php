<?php

namespace Modules\Order\Console\Commands;

use Illuminate\Console\Command;
use Modules\Order\Models\OrderCreditNote;
use Modules\Order\Models\OrderInvoice;
use Modules\Order\Schemas\Document\DocumentPdfStatusEnum;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;

/**
 * Returns stuck D7 generation leases: rows left in `generating` past
 * the lease window (default 30 minutes) go back to
 * pending_generation, so a crashed worker cannot wedge a document —
 * the next generation attempt simply re-claims.
 */
class SweepGeneratingDocumentsCommand extends Command
{
    protected $signature = 'order:sweep-generating-documents {--lease-minutes=30}';

    protected $description = 'Release expired PDF generation leases on invoices and credit notes';

    public function handle(): int
    {
        $leaseMinutes = max(1, (int) $this->option('lease-minutes'));
        $cutoff = now()->subMinutes($leaseMinutes);

        $released = 0;

        foreach ([OrderInvoice::class, OrderCreditNote::class] as $model) {
            $released += $model::query()
                ->where(OrderInvoiceSchema::PDF_STATUS, DocumentPdfStatusEnum::GENERATING->value)
                ->where(OrderInvoiceSchema::GENERATION_CLAIMED_AT, '<', $cutoff)
                ->update([
                    OrderInvoiceSchema::PDF_STATUS => DocumentPdfStatusEnum::PENDING_GENERATION->value,
                    OrderInvoiceSchema::GENERATION_CLAIMED_AT => null,
                ]);
        }

        $this->info($released === 0
            ? 'No expired generation leases.'
            : "Released {$released} expired generation lease(s) back to pending_generation.");

        return self::SUCCESS;
    }
}
