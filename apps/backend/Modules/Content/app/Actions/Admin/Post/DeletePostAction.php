<?php

namespace Modules\Content\Actions\Admin\Post;

use Modules\Content\Models\FeaturedItem;
use Modules\Content\Schemas\FeaturedItem\FeaturedItemSchema;

class DeletePostAction extends AbstractPostAction
{
    public function handle(string $id): bool
    {
        $record = $this->model->findOrFail($id);

        // Posts hard-delete; the polymorphic featured row has no FK
        // cascade, so remove it here to avoid an orphan.
        FeaturedItem::query()
            ->where(FeaturedItemSchema::FEATUREDABLE_TYPE, FeaturedItemSchema::TYPE_POST)
            ->where(FeaturedItemSchema::FEATUREDABLE_ID, (int) $id)
            ->delete();

        return $record->delete();
    }
}
