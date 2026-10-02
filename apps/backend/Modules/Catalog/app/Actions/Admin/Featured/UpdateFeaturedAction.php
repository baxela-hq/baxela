<?php

namespace Modules\Catalog\Actions\Admin\Featured;

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
     * @return array{product: \Illuminate\Support\Collection, category: \Illuminate\Support\Collection}
     */
    public function handle(array $productIds, array $categoryIds): array
    {
        DB::transaction(function () use ($productIds, $categoryIds): void {
            FeaturedItem::query()->delete();

            $this->insert(FeaturedItemSchema::TYPE_PRODUCT, $productIds);
            $this->insert(FeaturedItemSchema::TYPE_CATEGORY, $categoryIds);
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
