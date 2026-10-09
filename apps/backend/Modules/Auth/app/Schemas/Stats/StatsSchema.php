<?php

namespace Modules\Auth\Schemas\Stats;

/**
 * Response keys for the admin dashboard customer stats payload — no backing
 * table, the aggregates are computed by ShowStatsAction over users.
 */
class StatsSchema
{
    public const string RES_CUSTOMERS_COUNT = 'customers_count';

    public const string RES_NEW_CUSTOMERS_THIS_MONTH = 'new_customers_this_month';

    public const string RES_CUSTOMERS_CHANGE_PERCENT = 'customers_change_percent';
}
