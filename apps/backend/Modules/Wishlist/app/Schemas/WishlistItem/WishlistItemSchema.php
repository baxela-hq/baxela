<?php

namespace Modules\Wishlist\Schemas\WishlistItem;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Wishlist\Schemas\Module;

class WishlistItemSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'wishlist_items';

    public const string USER_ID = 'user_id';

    public const string PRODUCT_ID = 'product_id';

    /**
     * Runtime-only attribute: the ProductSummary DTO attached by the list
     * action (never stored — products are resolved through the Catalog
     * gateway, not a relation).
     */
    public const string ATTR_PRODUCT_SUMMARY = 'productSummary';
}
