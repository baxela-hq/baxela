<?php

namespace Modules\Catalog\Actions\Admin\Category;

use Modules\Catalog\Models\Category;
use Modules\Catalog\Schemas\Category\CategoryAttributeSchema;
use Modules\Catalog\Schemas\Category\CategorySchema;

class CreateCategoryAction
{
    public function handle(array $data): Category
    {
        $body = [
            CategorySchema::PARENT_ID => $data[CategorySchema::PARENT_ID],
            CategorySchema::POSITION => $data[CategorySchema::POSITION],
        ];
        $record = Category::query()->create($body);

        $translations = $data[CategorySchema::RES_TRANSLATIONS];
        foreach ($translations as $translation) {
            $record->translations()->create($translation);
        }

        $record->attributes()->attach($this->attributePivotData($data));

        $record = $record->refresh();

        return $record->load(CategorySchema::RES_TRANSLATIONS, CategorySchema::RES_ATTRIBUTES);
    }

    /**
     * Map the request's attribute id order to pivot positions.
     */
    private function attributePivotData(array $data): array
    {
        $pivot = [];
        $attributeIds = $data[CategoryAttributeSchema::REQ_ATTRIBUTE_IDS] ?? [];
        foreach (array_values($attributeIds) as $index => $attributeId) {
            $pivot[$attributeId] = [CategoryAttributeSchema::POSITION => $index];
        }

        return $pivot;
    }
}
