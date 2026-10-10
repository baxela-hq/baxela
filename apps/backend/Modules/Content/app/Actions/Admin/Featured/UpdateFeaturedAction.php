<?php

namespace Modules\Content\Actions\Admin\Featured;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Content\Models\FeaturedItem;
use Modules\Content\Schemas\FeaturedItem\FeaturedItemSchema;

class UpdateFeaturedAction
{
    public function __construct(protected ListFeaturedAction $listAction) {}

    /**
     * Full sync: replaces the whole table with the incoming selection.
     * Array order encodes the position.
     *
     * @param  array<int, int>  $postIds
     * @return array{post: Collection}
     */
    public function handle(array $postIds): array
    {
        DB::transaction(function () use ($postIds): void {
            FeaturedItem::query()->delete();

            $this->insert(FeaturedItemSchema::TYPE_POST, $postIds);
        });

        return $this->listAction->handle();
    }

    private function insert(string $type, array $ids): void
    {
        $now = now();

        $rows = collect($ids)->values()->map(fn ($id, $index) => [
            FeaturedItemSchema::FEATUREDABLE_TYPE => $type,
            FeaturedItemSchema::FEATUREDABLE_ID => $id,
            FeaturedItemSchema::POSITION => $index + 1,
            FeaturedItemSchema::CREATED_AT => $now,
            FeaturedItemSchema::UPDATED_AT => $now,
        ])->all();

        if ($rows !== []) {
            FeaturedItem::query()->insert($rows);
        }
    }
}
