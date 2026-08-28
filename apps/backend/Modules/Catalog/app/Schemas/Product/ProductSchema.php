<?php

namespace Modules\Catalog\Schemas\Product;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Core\Schemas\Shared\SoftDeleteTrait;

class ProductSchema
{
    use PkAndTimestampsTrait;
    use SoftDeleteTrait;

    public const string TABLE = Module::DB_PREFIX.'products';

    public const string TYPE = 'type';

    public const string STATUS = 'status';

    public const string IS_PUBLISHED = 'is_published';

    public const string RES_SHIPPING = 'shipping';

    public const string RES_OPTIONS = 'options';

    public const string RES_OPTION_VALUES = 'optionValues';

    public const string RES_VARIANTS = 'variants';

    public const string RES_CATEGORIES = 'categories';

    public const string RES_TRANSLATIONS = 'translations';

    public const string RES_SEO = 'seo';

    public const string RES_IMAGES = 'images';

    public const string RES_ATTRIBUTES = 'attributes';

    public const string RES_ATTRIBUTE_VALUES = 'attributeValues';
}
