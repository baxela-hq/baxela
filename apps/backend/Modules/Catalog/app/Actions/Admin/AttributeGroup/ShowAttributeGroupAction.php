<?php

namespace Modules\Catalog\Actions\Admin\AttributeGroup;

use Modules\Catalog\Models\AttributeGroup;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;

class ShowAttributeGroupAction
{
    public function handle(string $id): AttributeGroup
    {
        return AttributeGroup::query()
            ->with(
                AttributeGroupSchema::RES_TRANSLATIONS,
                AttributeGroupSchema::RES_ATTRIBUTES.'.'.AttributeSchema::RES_TRANSLATIONS,
            )
            ->findOrFail($id);
    }
}
