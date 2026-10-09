<?php

namespace Modules\Discount\Schemas\Promotion;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Discount\Schemas\Module;

class PromotionSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'promotions';

    /**
     * Internal admin-facing label; never shown to customers.
     */
    public const string NAME = 'name';

    /**
     * Which products the promotion covers. `all` = the whole catalog
     * (pivot tables must stay empty); `specific` = union of the selected
     * products and categories, with at least one selection required.
     */
    public const string SCOPE = 'scope';

    public const string TYPE = 'type';

    /**
     * VALUE semantics (major units, decimal(12,2)):
     *   percent → 15.00 == 15% (NOT a 0.15 fraction), allowed 0.01–100
     *   fixed   → a major-unit amount off each unit's base price
     */
    public const string VALUE = 'value';

    /**
     * Validity window in UTC (inclusive bounds; null = unbounded).
     */
    public const string STARTS_AT = 'starts_at';

    public const string ENDS_AT = 'ends_at';

    /**
     * Tie-breaker when several active promotions match a product with the
     * same specificity — higher wins. Specificity itself (product >
     * category > store-wide) always outranks priority.
     */
    public const string PRIORITY = 'priority';

    public const string IS_ACTIVE = 'is_active';

    /**
     * API payload keys for the scope selections (never column names on
     * this table — the selections live in the pivot tables).
     */
    public const string PRODUCT_IDS = 'product_ids';

    public const string CATEGORY_IDS = 'category_ids';

    /**
     * Runtime-only attributes: the scope selections attached by the admin
     * list action (batched) or resolved from the pivots (show) — the
     * resource reads them instead of a per-row query.
     */
    public const string ATTR_PRODUCT_IDS = 'productIds';

    public const string ATTR_CATEGORY_IDS = 'categoryIds';
}
