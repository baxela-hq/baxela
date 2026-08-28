<?php

namespace Modules\Catalog\Actions\Admin\AttributeValue;

use Modules\Catalog\Models\AttributeValue;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;

class DeleteAttributeValueAction
{
    public function handle(string $valueId): bool
    {
        $record = AttributeValue::query()
            ->where(AttributeValueSchema::ID, $valueId)
            ->firstOrFail();

        return $record->delete();
    }
}
