<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Models\FeaturedItem;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * The production upgrade path: a database still carrying the legacy
 * is_featured flags must end up with positioned featured items, and the
 * flag columns must be gone.
 */
it('migrates legacy featured flags into featured items and drops the columns', function () {
    // Restore the pre-feature schema: drop the featured table, re-add flags.
    $this->artisan('migrate:rollback', [
        '--path' => 'Modules/Catalog/database/migrations/2026_10_02_000002_migrate_featured_flags_and_drop_columns.php',
    ]);
    $this->artisan('migrate:rollback', [
        '--path' => 'Modules/Catalog/database/migrations/2026_10_02_000001_create_featured_items_table.php',
    ]);

    expect(Schema::hasTable(FeaturedItemSchema::TABLE))->toBeFalse()
        ->and(Schema::hasColumn(CategorySchema::TABLE, 'is_featured'))->toBeTrue()
        ->and(Schema::hasColumn(ProductSchema::TABLE, 'is_featured'))->toBeTrue();

    // Legacy rows: categories ordered by position (nulls last), products
    // newest-first, plus unflagged and soft-deleted rows that must not move.
    $now = now()->toDateTimeString();

    $categoryId = DB::table(CategorySchema::TABLE)->insertGetId([
        'parent_id' => null, 'position' => 1, 'image_media_id' => null,
        'image_url' => '/storage/categories/keep.png',
        'is_featured' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table(CategorySchema::TABLE)->insert([
        'parent_id' => null, 'position' => 2, 'image_media_id' => null,
        'image_url' => null, 'is_featured' => false,
        'created_at' => $now, 'updated_at' => $now,
    ]);

    $olderProductId = DB::table(ProductSchema::TABLE)->insertGetId([
        'type' => 'simple', 'status' => 'in_stock', 'is_published' => true,
        'is_featured' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $newerProductId = DB::table(ProductSchema::TABLE)->insertGetId([
        'type' => 'simple', 'status' => 'in_stock', 'is_published' => true,
        'is_featured' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $trashedProductId = DB::table(ProductSchema::TABLE)->insertGetId([
        'type' => 'simple', 'status' => 'in_stock', 'is_published' => true,
        'is_featured' => true, 'deleted_at' => $now,
        'created_at' => $now, 'updated_at' => $now,
    ]);

    // Re-run the two featured migrations.
    $this->artisan('migrate', [
        '--path' => 'Modules/Catalog/database/migrations/2026_10_02_000001_create_featured_items_table.php',
    ]);
    $this->artisan('migrate', [
        '--path' => 'Modules/Catalog/database/migrations/2026_10_02_000002_migrate_featured_flags_and_drop_columns.php',
    ]);

    expect(Schema::hasColumn(CategorySchema::TABLE, 'is_featured'))->toBeFalse()
        ->and(Schema::hasColumn(ProductSchema::TABLE, 'is_featured'))->toBeFalse()
        // The category keeps its image; featured state moved to the table.
        ->and(DB::table(CategorySchema::TABLE)->where(CategorySchema::ID, $categoryId)
            ->value(CategorySchema::IMAGE_URL))->toBe('/storage/categories/keep.png');

    $categoryPositions = FeaturedItem::query()
        ->where(FeaturedItemSchema::FEATUREDABLE_TYPE, FeaturedItemSchema::TYPE_CATEGORY)
        ->pluck(FeaturedItemSchema::POSITION, FeaturedItemSchema::FEATUREDABLE_ID);
    expect($categoryPositions->all())->toBe([$categoryId => 1]);

    // Products keep their storefront order (newest first) and the
    // soft-deleted row is not carried over.
    $productPositions = FeaturedItem::query()
        ->where(FeaturedItemSchema::FEATUREDABLE_TYPE, FeaturedItemSchema::TYPE_PRODUCT)
        ->pluck(FeaturedItemSchema::POSITION, FeaturedItemSchema::FEATUREDABLE_ID);
    expect($productPositions->all())->toBe([
        $newerProductId => 1,
        $olderProductId => 2,
    ])
        ->and($productPositions->has($trashedProductId))->toBeFalse()
        ->and(FeaturedItem::query()->count())->toBe(3);
});
