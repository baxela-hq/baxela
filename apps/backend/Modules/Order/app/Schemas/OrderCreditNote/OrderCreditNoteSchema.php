<?php

namespace Modules\Order\Schemas\OrderCreditNote;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Order\Schemas\Module;

class OrderCreditNoteSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'credit_notes';

    public const string INVOICE_ID = 'invoice_id';

    /**
     * The finalized refund this note documents (D5/D10). Nullable: manual
     * credit notes (a planned follow-up) adjust an invoice without a
     * refund. Unique when set — the idempotency key for the
     * refund-backed automation.
     */
    public const string REFUND_UUID = 'refund_uuid';

    public const string NUMBER = 'number';

    public const string STATUS = 'status';

    public const string ISSUED_AT = 'issued_at';

    public const string VOIDED_AT = 'voided_at';

    public const string VOID_REASON = 'void_reason';

    public const string CURRENCY_CODE = 'currency_code';

    public const string AMOUNT = 'amount';

    public const string REASON = 'reason';

    public const string PDF_STATUS = 'pdf_status';

    public const string PDF_DISK = 'pdf_disk';

    public const string PDF_PATH = 'pdf_path';

    public const string PDF_CHECKSUM = 'pdf_checksum';

    public const string PDF_GENERATED_AT = 'pdf_generated_at';

    public const string TEMPLATE_VERSION = 'template_version';

    public const string GENERATION_CLAIMED_AT = 'generation_claimed_at';
}
