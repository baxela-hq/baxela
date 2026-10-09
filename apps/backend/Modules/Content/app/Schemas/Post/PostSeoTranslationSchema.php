<?php

namespace Modules\Content\Schemas\Post;

use Modules\Content\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class PostSeoTranslationSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'post_seo_translations';

    public const string POST_ID = 'post_id';

    public const string LANGUAGE_ID = 'language_id';

    public const string META_TITLE = 'meta_title';

    public const string META_DESCRIPTION = 'meta_description';

    public const string OPEN_GRAPH_TITLE = 'open_graph_title';

    public const string OPEN_GRAPH_DESCRIPTION = 'open_graph_description';

    public const string COL_LANGUAGE = 'language';

    public const string REQ_LANGUAGE = 'language';
}
