<?php

namespace Modules\Catalog\Schemas\Product;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class ProductSeoTranslationSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'product_seo_translations';

    public const string PRODUCT_ID = 'product_id';

    public const string LANGUAGE_ID = 'language_id';

    public const string META_TITLE = 'meta_title';

    public const string META_DESCRIPTION = 'meta_description';

    public const string OPEN_GRAPH_TITLE = 'open_graph_title';

    public const string OPEN_GRAPH_DESCRIPTION = 'open_graph_description';

    public const string COL_LANGUAGE = 'language';

    public const string REQ_LANGUAGE = 'language';
}
