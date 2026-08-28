<?php

namespace Modules\Catalog\Actions\Admin\Attribute;

use Modules\Catalog\Models\Attribute;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;

class ShowAttributeAction
{
    public function handle(string $id): Attribute
    {
        return Attribute::query()
            ->with(
                AttributeSchema::RES_TRANSLATIONS,
                AttributeSchema::RES_VALUES.'.'.AttributeValueSchema::RES_TRANSLATIONS,
                AttributeSchema::RES_GROUP.'.'.AttributeGroupSchema::RES_TRANSLATIONS,
            )
            ->findOrFail($id);
    }
}
