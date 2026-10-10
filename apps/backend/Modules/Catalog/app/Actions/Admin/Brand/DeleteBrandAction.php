<?php

namespace Modules\Catalog\Actions\Admin\Brand;

use Modules\Catalog\Models\FeaturedItem;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;

class DeleteBrandAction extends AbstractBrandAction
{
    public function handle(string $id): bool
    {
        $record = $this->model->query()->findOrFail($id);

        // Brands hard-delete; the polymorphic featured row has no FK
        // cascade, so remove it here to avoid an orphan.
        FeaturedItem::query()
            ->where(FeaturedItemSchema::FEATUREDABLE_TYPE, FeaturedItemSchema::TYPE_BRAND)
            ->where(FeaturedItemSchema::FEATUREDABLE_ID, (int) $id)
            ->delete();

        return $record->delete();
    }
}
