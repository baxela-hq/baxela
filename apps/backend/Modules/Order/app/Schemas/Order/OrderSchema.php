<?php

namespace Modules\Order\Schemas\Order;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Order\Schemas\Module;

class OrderSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'orders';

    public const string USER_ID = 'user_id';

    /**
     * Opaque customer-facing code; the sequential int PK stays internal.
     */
    public const string ORDER_CODE = 'order_code';

    public const string STATUS = 'status';

    public const string PAYMENT_STATUS = 'payment_status';

    public const string PAID_AT = 'paid_at';

    public const string TOTAL_AMOUNT = 'total_amount';

    /**
     * Currency the order was placed in; snapshotted at checkout so later
     * default-currency changes never rewrite history.
     */
    public const string CURRENCY_ID = 'currency_id';

    public const string SHIPPING_METHOD_ID = 'shipping_method_id';

    public const string SHIPPING_METHOD_NAME = 'shipping_method_name';

    public const string SHIPPING_COST = 'shipping_cost';

    /**
     * Coupon snapshot of what was redeemed at purchase time — minimal by
     * design; later coupon edits never rewrite a past order's discount.
     * coupon_id references discount_coupons loosely (no cross-module FK).
     */
    public const string COUPON_ID = 'coupon_id';

    public const string COUPON_CODE = 'coupon_code';

    public const string DISCOUNT_AMOUNT = 'discount_amount';

    /**
     * Tax snapshot of what was charged at purchase time: tax_amount is the
     * Σ of the item taxes PLUS the shipping tax (added into the total
     * exactly once); the shipping_tax_* columns explain that portion
     * independently so historical orders stay auditable after rate or
     * class changes. shipping_tax_class_id references tax_classes loosely
     * (no cross-module FK).
     */
    public const string TAX_AMOUNT = 'tax_amount';

    public const string SHIPPING_TAX_AMOUNT = 'shipping_tax_amount';

    public const string SHIPPING_TAX_RATE = 'shipping_tax_rate';

    public const string SHIPPING_TAX_CLASS_ID = 'shipping_tax_class_id';

    /**
     * Σ successful refund ledger amounts across all the order's payments,
     * maintained by OrderGateway::applyRefund() as refunds finalize.
     */
    public const string REFUNDED_AMOUNT = 'refunded_amount';

    public const string EXPIRES_AT = 'expires_at';

    public const string RES_ITEMS = 'items';

    public const string RES_ADDRESSES = 'addresses';
}
