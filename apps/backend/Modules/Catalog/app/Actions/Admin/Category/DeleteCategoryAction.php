<?php

namespace Modules\Catalog\Actions\Admin\Category;

use Modules\Catalog\Models\FeaturedItem;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;

class DeleteCategoryAction extends AbstractCategoryAction
{
    public function handle(string $id): bool
    {
        $record = $this->model->query()->findOrFail($id);

        // Categories hard-delete; the polymorphic featured row has no FK
        // cascade, so remove it here to avoid an orphan.
        FeaturedItem::query()
            ->where(FeaturedItemSchema::FEATUREDABLE_TYPE, FeaturedItemSchema::TYPE_CATEGORY)
            ->where(FeaturedItemSchema::FEATUREDABLE_ID, (int) $id)
            ->delete();

        return $record->delete();
    }
}
