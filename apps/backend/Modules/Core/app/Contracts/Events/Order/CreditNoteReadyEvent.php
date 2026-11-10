<?php

namespace Modules\Core\Contracts\Events\Order;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

/**
 * Fired once a credit note's PDF artifact reaches ready (gap-B1 Phase
 * 4): mirrors InvoiceReadyEvent — the email attaches the stored bytes,
 * so it can only fire after generation succeeded. Carries the artifact
 * coordinates so listeners never import Order models.
 */
class CreditNoteReadyEvent extends AbstractBaseEvent
{
    public int $credit_note_id;

    public int $user_id;

    public string $order_code;

    public string $credit_note_number;

    public string $invoice_number;

    public string $pdf_disk;

    public string $pdf_path;

    public ?string $locale = null;
}
