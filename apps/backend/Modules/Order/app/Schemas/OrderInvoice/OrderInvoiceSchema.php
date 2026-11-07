<?php

namespace Modules\Order\Schemas\OrderInvoice;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Order\Schemas\Module;

class OrderInvoiceSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'invoices';

    public const string ORDER_ID = 'order_id';

    /**
     * Replacement counter within one order: 1 for the first issuance,
     * incrementing on void + reissue. One ACTIVE invoice per order at any
     * time; every revision is retained for audit.
     */
    public const string REVISION = 'revision';

    public const string NUMBER = 'number';

    public const string STATUS = 'status';

    public const string ISSUED_AT = 'issued_at';

    public const string VOIDED_AT = 'voided_at';

    public const string VOID_REASON = 'void_reason';

    /**
     * Frozen snapshot block (D4): everything below is copied at issue time
     * and never re-derived from live order rows or current settings.
     */
    public const string CURRENCY_CODE = 'currency_code';

    public const string SUBTOTAL_AMOUNT = 'subtotal_amount';

    public const string DISCOUNT_AMOUNT = 'discount_amount';

    public const string COUPON_CODE = 'coupon_code';

    public const string SHIPPING_AMOUNT = 'shipping_amount';

    public const string SHIPPING_TAX_AMOUNT = 'shipping_tax_amount';

    public const string TAX_AMOUNT = 'tax_amount';

    public const string TOTAL_AMOUNT = 'total_amount';

    public const string BILLING_ADDRESS = 'billing_address';

    public const string BUYER_IDENTITY = 'buyer_identity';

    public const string SELLER_IDENTITY = 'seller_identity';

    public const string META = 'meta';

    /**
     * PDF artifact lifecycle (D7): the legal record is the row; the PDF is
     * a regenerable representation tracked separately.
     */
    public const string PDF_STATUS = 'pdf_status';

    public const string PDF_DISK = 'pdf_disk';

    public const string PDF_PATH = 'pdf_path';

    public const string PDF_CHECKSUM = 'pdf_checksum';

    public const string PDF_GENERATED_AT = 'pdf_generated_at';

    public const string TEMPLATE_VERSION = 'template_version';

    /**
     * Generation lease (D7): set when a worker claims pending_generation;
     * a sweep re-arms rows whose lease expired so a crashed process cannot
     * wedge the document.
     */
    public const string GENERATION_CLAIMED_AT = 'generation_claimed_at';

    public const string RES_ITEMS = 'items';
}
