<?php

namespace Modules\Catalog\Schemas\Product;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Core\Schemas\Shared\SoftDeleteTrait;

class ProductTranslationSchema
{
    use PkAndTimestampsTrait;
    use SoftDeleteTrait;

    public const string TABLE = Module::DB_PREFIX.'product_translations';

    public const string PRODUCT_ID = 'product_id';

    public const string LANGUAGE_ID = 'language_id';

    public const string TITLE = 'title';

    public const string SLUG = 'slug';

    public const string CONTENT = 'content';

    public const string DESCRIPTION = 'description';

    public const string COL_LANGUAGE = 'language';

    public const string REQ_LANGUAGE = 'language';
}
