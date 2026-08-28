<?php

namespace Modules\Catalog\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case PRODUCT_CREATION_FAILED = 'catalog.product.creation_failed';

    case ATTRIBUTE_GROUP_CREATION_FAILED = 'catalog.attribute_group.creation_failed';

    case ATTRIBUTE_GROUP_UPDATE_FAILED = 'catalog.attribute_group.update_failed';

    case ATTRIBUTE_GROUP_NOT_EMPTY = 'catalog.attribute_group.not_empty';

    case ATTRIBUTE_CREATION_FAILED = 'catalog.attribute.creation_failed';

    case ATTRIBUTE_UPDATE_FAILED = 'catalog.attribute.update_failed';

    case ATTRIBUTE_VALUE_CREATION_FAILED = 'catalog.attribute_value.creation_failed';

    case ATTRIBUTE_VALUE_UPDATE_FAILED = 'catalog.attribute_value.update_failed';

    case ATTRIBUTE_TEMPLATE_CREATION_FAILED = 'catalog.attribute_template.creation_failed';

    case ATTRIBUTE_TEMPLATE_UPDATE_FAILED = 'catalog.attribute_template.update_failed';
}
