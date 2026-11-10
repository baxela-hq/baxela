<?php

namespace Modules\Core\Contracts\Events\Order;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

/**
 * Fired once a document's PDF artifact reaches ready (gap-B1 Phase 3):
 * the email notification attaches the STORED bytes, so it can only be
 * sent after generation succeeded. Delivery is the listener's concern —
 * a notification failure never re-issues or re-numbers the document.
 *
 * Carries everything the notification needs — including the artifact's
 * storage coordinates — so no listener has to reach into the Order
 * module's models (cross-module discipline: contracts only).
 */
class InvoiceReadyEvent extends AbstractBaseEvent
{
    public int $invoice_id;

    public int $user_id;

    public string $order_code;

    public string $invoice_number;

    public string $pdf_disk;

    public string $pdf_path;

    public ?string $locale = null;
}
