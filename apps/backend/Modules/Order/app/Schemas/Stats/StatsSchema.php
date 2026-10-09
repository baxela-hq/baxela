<?php

namespace Modules\Order\Schemas\Stats;

/**
 * Response keys for the admin dashboard stats payload — no backing table,
 * the aggregates are computed by ShowStatsAction over orders/order_items.
 */
class StatsSchema
{
    public const string RES_TOTAL_REVENUE = 'total_revenue';

    public const string RES_REVENUE_CHANGE_PERCENT = 'revenue_change_percent';

    public const string RES_ORDERS_COUNT = 'orders_count';

    public const string RES_ORDERS_CHANGE_PERCENT = 'orders_change_percent';

    public const string RES_PENDING_ORDERS_COUNT = 'pending_orders_count';

    public const string RES_CANCELLED_ORDERS_COUNT = 'cancelled_orders_count';

    public const string RES_PAID_ORDERS_COUNT = 'paid_orders_count';

    public const string RES_AVG_ORDER_VALUE = 'avg_order_value';

    public const string RES_REVENUE_BY_MONTH = 'revenue_by_month';

    public const string RES_ORDERS_PER_DAY = 'orders_per_day';

    public const string RES_ORDERS_BY_STATUS = 'orders_by_status';

    public const string RES_TOP_PRODUCTS = 'top_products';

    public const string RES_RECENT_ORDERS = 'recent_orders';

    // Nested series keys.
    public const string RES_MONTH = 'month';

    public const string RES_TOTAL = 'total';

    public const string RES_DATE = 'date';

    public const string RES_COUNT = 'count';

    public const string RES_STATUS = 'status';

    public const string RES_NAME = 'name';

    public const string RES_QUANTITY = 'quantity';
}
