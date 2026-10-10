<?php

namespace Modules\Content\Actions\Admin\Featured;

use Illuminate\Support\Collection;
use Modules\Content\Models\FeaturedItem;
use Modules\Content\Models\Post;
use Modules\Content\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Content\Schemas\Post\PostSchema;

class ListFeaturedAction
{
    /**
     * The posts section ordered by featured position.
     *
     * @return array{post: Collection<Post>}
     */
    public function handle(): array
    {
        return [
            FeaturedItemSchema::RES_POST => Post::query()
                ->whereHas('featuredItem')
                ->with(PostSchema::RES_TRANSLATIONS)
                ->orderBy($this->positionSubquery(
                    FeaturedItemSchema::TYPE_POST,
                    PostSchema::TABLE.'.'.PostSchema::ID
                ))
                ->get(),
        ];
    }

    private function positionSubquery(string $type, string $qualifiedColumn)
    {
        return FeaturedItem::query()
            ->select(FeaturedItemSchema::POSITION)
            ->where(FeaturedItemSchema::FEATUREDABLE_TYPE, $type)
            ->whereColumn(FeaturedItemSchema::FEATUREDABLE_ID, $qualifiedColumn);
    }
}
