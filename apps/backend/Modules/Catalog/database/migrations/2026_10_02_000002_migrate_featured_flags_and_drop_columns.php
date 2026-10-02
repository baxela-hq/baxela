<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;

return new class extends Migration
{
    /**
     * Move the legacy is_featured flags into catalog_featured_items,
     * then drop the columns. Category image columns are untouched.
     */
    public function up(): void
    {
        $this->migrateCategories();
        $this->migrateProducts();

        Schema::table(CategorySchema::TABLE, function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });

        Schema::table(ProductSchema::TABLE, function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table(CategorySchema::TABLE, function (Blueprint $table) {
            $table->boolean('is_featured')->default(false);
        });

        Schema::table(ProductSchema::TABLE, function (Blueprint $table) {
            $table->boolean('is_featured')->default(false);
        });

        $this->restoreFlags(CategorySchema::TABLE, FeaturedItemSchema::TYPE_CATEGORY);
        $this->restoreFlags(ProductSchema::TABLE, FeaturedItemSchema::TYPE_PRODUCT);
    }

    /**
     * Keep the ordering the storefront used to serve: category position
     * (nulls last), then id.
     */
    private function migrateCategories(): void
    {
        $ids = DB::table(CategorySchema::TABLE)
            ->where('is_featured', true)
            ->orderByRaw('position IS NULL')
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id');

        $this->insertFeaturedItems(FeaturedItemSchema::TYPE_CATEGORY, $ids);
    }

    /**
     * Keep the ordering the storefront used to serve: newest first.
     * Soft-deleted products never surfaced publicly, so skip them.
     */
    private function migrateProducts(): void
    {
        $ids = DB::table(ProductSchema::TABLE)
            ->where('is_featured', true)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->pluck('id');

        $this->insertFeaturedItems(FeaturedItemSchema::TYPE_PRODUCT, $ids);
    }

    private function insertFeaturedItems(string $type, $ids): void
    {
        $now = now()->toDateTimeString();

        $rows = $ids->values()->map(fn ($id, $index) => [
            FeaturedItemSchema::FEATUREDABLE_TYPE => $type,
            FeaturedItemSchema::FEATUREDABLE_ID => $id,
            FeaturedItemSchema::POSITION => $index + 1,
            FeaturedItemSchema::CREATED_AT => $now,
            FeaturedItemSchema::UPDATED_AT => $now,
        ])->all();

        if ($rows !== []) {
            DB::table(FeaturedItemSchema::TABLE)->insert($rows);
        }
    }

    private function restoreFlags(string $table, string $type): void
    {
        $ids = DB::table(FeaturedItemSchema::TABLE)
            ->where(FeaturedItemSchema::FEATUREDABLE_TYPE, $type)
            ->pluck(FeaturedItemSchema::FEATUREDABLE_ID);

        if ($ids->isNotEmpty()) {
            DB::table($table)->whereIn('id', $ids)->update(['is_featured' => true]);
        }
    }
};
