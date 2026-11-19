<?php

namespace Modules\Core\Contracts\Events\Order;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class OrderPaidEvent extends AbstractBaseEvent
{
    public int $id;

    public int $user_id;

    public string $order_code;

    public string $status;

    public string $payment_status;

    public float $total_amount;

    public ?string $locale = null;

    /**
     * Base (charging) currency of the transaction — the order row's
     * currency_id, so notifications survive later default-currency
     * changes. Null on legacy events; resolvers fall back to the
     * store default then.
     */
    public ?int $currency_id = null;

    /**
     * Display currency the customer chose at checkout — what they
     * saw. Null means the order was placed in the base currency.
     */
    public ?string $display_currency_code = null;

    /**
     * Immutable checkout-time exchange rate (decimal(16,8) string,
     * display units per base unit). Never the live rate.
     */
    public ?string $display_fx_rate = null;
}
