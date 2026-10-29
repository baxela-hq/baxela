<?php

namespace Modules\Catalog\Actions\Admin\Featured;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\FeaturedItem;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;

class UpdateFeaturedAction
{
    public function __construct(protected ListFeaturedAction $listAction) {}

    /**
     * Full sync: replaces the whole table with the incoming selections.
     * Array order encodes the position within each section.
     *
     * @param  array<int, int>  $productIds
     * @param  array<int, int>  $categoryIds
     * @param  array<int, int>  $brandIds
     * @return array{product: Collection, category: Collection, brand: Collection}
     */
    public function handle(array $productIds, array $categoryIds, array $brandIds): array
    {
        DB::transaction(function () use ($productIds, $categoryIds, $brandIds): void {
            FeaturedItem::query()->delete();

            $this->insert(FeaturedItemSchema::TYPE_PRODUCT, $productIds);
            $this->insert(FeaturedItemSchema::TYPE_CATEGORY, $categoryIds);
            $this->insert(FeaturedItemSchema::TYPE_BRAND, $brandIds);
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
