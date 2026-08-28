<?php

namespace Modules\Catalog\Actions\Admin\Category;

use Modules\Catalog\Models\Category;
use Modules\Catalog\Schemas\Category\CategoryAttributeSchema;
use Modules\Catalog\Schemas\Category\CategorySchema;

class UpdateCategoryAction
{
    public function handle(string $id, array $data): Category
    {
        $record = Category::query()->findOrFail($id);
        $body = [
            CategorySchema::POSITION => $data[CategorySchema::POSITION],
        ];
        $record->update($body);

        $record->translations()->delete();
        $translations = $data[CategorySchema::RES_TRANSLATIONS];
        foreach ($translations as $translation) {
            $record->translations()->create($translation);
        }

        $record->attributes()->sync($this->attributePivotData($data));

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
