<?php

namespace Modules\Catalog\Actions\Admin\AttributeTemplate;

use Modules\Catalog\Models\AttributeTemplate;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;

class ShowAttributeTemplateAction
{
    public function handle(string $id): AttributeTemplate
    {
        $groups = AttributeTemplateSchema::RES_GROUPS;

        return AttributeTemplate::query()
            ->with(
                $groups.'.'.AttributeGroupSchema::RES_TRANSLATIONS,
                $groups.'.'.AttributeGroupSchema::RES_ATTRIBUTES.'.'.AttributeSchema::RES_TRANSLATIONS,
                $groups.'.'.AttributeGroupSchema::RES_ATTRIBUTES.'.'.AttributeSchema::RES_VALUES.'.'.AttributeValueSchema::RES_TRANSLATIONS,
            )
            ->findOrFail($id);
    }
}
