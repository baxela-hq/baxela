<?php

namespace Modules\Discount\Schemas\Promotion;

enum ScopeTypeEnum: string
{
    /**
     * Every product in the catalog.
     */
    case ALL = 'all';

    /**
     * The union of the promotion's selected products and the products of
     * its selected categories. At least one selection is required — an
     * empty selection can never silently degrade to store-wide.
     */
    case SPECIFIC = 'specific';
}
