<?php

namespace Modules\Catalog\Actions\Admin\AttributeValue;

use Modules\Catalog\Models\AttributeValue;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;

class ShowAttributeValueAction
{
    public function handle(string $attributeId, string $valueId): AttributeValue
    {
        return AttributeValue::query()
            ->where(AttributeValueSchema::ATTRIBUTE_ID, $attributeId)
            ->where(AttributeValueSchema::ID, $valueId)
            ->with(
                AttributeValueSchema::RES_TRANSLATIONS,
            )
            ->firstOrFail();
    }
}
