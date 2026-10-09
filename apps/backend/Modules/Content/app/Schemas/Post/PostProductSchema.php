<?php

namespace Modules\Content\Schemas\Post;

use Modules\Content\Schemas\Module;

class PostProductSchema
{
    public const string TABLE = Module::DB_PREFIX.'post_product';

    public const string POST_ID = 'post_id';

    public const string PRODUCT_ID = 'product_id';

    // Runtime-only attribute: the ProductSummary DTO attached by the admin
    // actions (never stored — products resolve through the Catalog gateway).
    public const string ATTR_PRODUCT_SUMMARY = 'productSummary';
}
