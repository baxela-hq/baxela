<?php

namespace Modules\Discount\Tests\Feature;

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Modules\Auth\Models\User;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Promotion;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Coupon\CouponTypeEnum;
use Modules\Discount\Schemas\Promotion\PromotionSchema;
use Modules\Discount\Schemas\Promotion\ScopeTypeEnum;
use Modules\Discount\Schemas\Redemption\RedemptionSchema;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/discount'.$endpoint;
    }


    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /** Coupon + promotion (scoped to a real catalog product/category) + one redemption. */
    public function seedTransferDiscount(): array
    {
        $coupon = Coupon::query()->create([
            CouponSchema::CODE => 'WELCOME10',
            CouponSchema::NAME => 'Welcome',
            CouponSchema::TYPE => CouponTypeEnum::PERCENT->value,
            CouponSchema::VALUE => '10.00',
            CouponSchema::USAGE_LIMIT => 100,
            CouponSchema::IS_ACTIVE => true,
        ]);

        $product = Product::query()->create(['type' => 'simple', 'status' => 'in_stock', 'is_published' => true]);
        $category = Category::query()->create(['parent_id' => null, 'position' => 1]);

        $promotion = Promotion::query()->create([
            PromotionSchema::NAME => 'Summer sale',
            PromotionSchema::SCOPE => ScopeTypeEnum::SPECIFIC->value,
            PromotionSchema::TYPE => CouponTypeEnum::PERCENT->value,
            PromotionSchema::VALUE => '15.00',
            PromotionSchema::PRIORITY => 1,
            PromotionSchema::IS_ACTIVE => true,
        ]);
        $promotion->syncScope([$product->id], [$category->id]);

        $redemption = Redemption::query()->create([
            RedemptionSchema::COUPON_ID => $coupon->id,
            RedemptionSchema::ORDER_ID => 501,
            RedemptionSchema::USER_ID => 7,
            RedemptionSchema::DISCOUNT_AMOUNT => '25.50',
        ]);

        return ['coupon' => $coupon, 'promotion' => $promotion, 'product' => $product, 'category' => $category, 'redemption' => $redemption];
    }


    /**
     * Export through the module's own data endpoint and return the
     * decoded envelope.
     *
     * @return array<string, mixed>
     */
    public function exportedDiscountDataFile(): array
    {
        return json_decode(
            $this->getJson($this->baseUrl('/admin/data/export'))->assertOk()->streamedContent(),
            true
        );
    }

    /** The multipart file field for the data-import endpoints. */
    public function transferFile(string $content, string $name = 'transfer.json'): File
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }
}
