<?php

namespace Modules\Discount\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Discount\Database\Factories\PromotionFactory;
use Modules\Discount\Schemas\Coupon\CouponTypeEnum;
use Modules\Discount\Schemas\Promotion\PromotionCategorySchema;
use Modules\Discount\Schemas\Promotion\PromotionProductSchema;
use Modules\Discount\Schemas\Promotion\PromotionSchema;
use Modules\Discount\Schemas\Promotion\ScopeTypeEnum;

/**
 * @mixin Builder
 */
/**
 * @property int $id
 * @property string $name
 * @property ScopeTypeEnum $scope
 * @property CouponTypeEnum $type
 * @property string $value
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $ends_at
 * @property int $priority
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product> $products
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Category> $categories
 */
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    protected $table = PromotionSchema::TABLE;

    protected $fillable = [
        PromotionSchema::NAME,
        PromotionSchema::SCOPE,
        PromotionSchema::TYPE,
        PromotionSchema::VALUE,
        PromotionSchema::STARTS_AT,
        PromotionSchema::ENDS_AT,
        PromotionSchema::PRIORITY,
        PromotionSchema::IS_ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            PromotionSchema::SCOPE => ScopeTypeEnum::class,
            PromotionSchema::TYPE => CouponTypeEnum::class,
            PromotionSchema::STARTS_AT => 'datetime',
            PromotionSchema::ENDS_AT => 'datetime',
            PromotionSchema::PRIORITY => 'integer',
            PromotionSchema::IS_ACTIVE => 'boolean',
        ];
    }

    /**
     * The selected catalog product ids. The pivots hold plain id columns —
     * never Eloquent relations to another module's models — so scope
     * syncing stays inside this module's own tables.
     *
     * Named "selected*" so it can never collide with the runtime
     * ATTR_PRODUCT_IDS attribute: Eloquent treats a method-named attribute
     * access as a relation load when the attribute itself is absent.
     *
     * @return Collection<int, int>
     */
    public function selectedProductIds(): Collection
    {
        return DB::table(PromotionProductSchema::TABLE)
            ->where(PromotionProductSchema::PROMOTION_ID, $this->{PromotionSchema::ID})
            ->pluck(PromotionProductSchema::PRODUCT_ID)
            ->map(fn ($id) => (int) $id);
    }

    /**
     * @return Collection<int, int>
     */
    public function selectedCategoryIds(): Collection
    {
        return DB::table(PromotionCategorySchema::TABLE)
            ->where(PromotionCategorySchema::PROMOTION_ID, $this->{PromotionSchema::ID})
            ->pluck(PromotionCategorySchema::CATEGORY_ID)
            ->map(fn ($id) => (int) $id);
    }

    /**
     * Replace the scope selections. Called inside the create/update
     * transaction so a pivot failure rolls the whole mutation back.
     */
    /**
     * @param  list<int>  $productIds
     * @param  list<int>  $categoryIds
     */
    public function syncScope(array $productIds, array $categoryIds): void
    {
        DB::table(PromotionProductSchema::TABLE)
            ->where(PromotionProductSchema::PROMOTION_ID, $this->{PromotionSchema::ID})
            ->delete();
        DB::table(PromotionCategorySchema::TABLE)
            ->where(PromotionCategorySchema::PROMOTION_ID, $this->{PromotionSchema::ID})
            ->delete();

        foreach (array_values(array_unique(array_map('intval', $productIds))) as $productId) {
            DB::table(PromotionProductSchema::TABLE)->insert([
                PromotionProductSchema::PROMOTION_ID => $this->{PromotionSchema::ID},
                PromotionProductSchema::PRODUCT_ID => $productId,
            ]);
        }

        foreach (array_values(array_unique(array_map('intval', $categoryIds))) as $categoryId) {
            DB::table(PromotionCategorySchema::TABLE)->insert([
                PromotionCategorySchema::PROMOTION_ID => $this->{PromotionSchema::ID},
                PromotionCategorySchema::CATEGORY_ID => $categoryId,
            ]);
        }
    }

    protected static function newFactory(): PromotionFactory
    {
        return PromotionFactory::new();
    }
}
