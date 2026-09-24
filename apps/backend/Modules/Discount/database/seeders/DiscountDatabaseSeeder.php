<?php

namespace Modules\Discount\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Coupon\CouponTypeEnum;

class DiscountDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);

        // A representative pair of coupons so the admin list and the
        // storefront checkout flow have something to exercise right after
        // seeding. firstOrCreate on the code keeps re-seeding
        // non-destructive: existing coupons and their redemption history
        // are never overwritten.
        Coupon::query()->firstOrCreate(
            [CouponSchema::CODE => 'WELCOME10'],
            [
                CouponSchema::NAME => 'Welcome offer',
                CouponSchema::TYPE => CouponTypeEnum::PERCENT,
                CouponSchema::VALUE => 10,
                CouponSchema::PER_USER_LIMIT => 1,
                CouponSchema::IS_ACTIVE => true,
            ],
        );

        Coupon::query()->firstOrCreate(
            [CouponSchema::CODE => 'SAVE5'],
            [
                CouponSchema::NAME => 'Five off orders above twenty',
                CouponSchema::TYPE => CouponTypeEnum::FIXED,
                CouponSchema::VALUE => 5,
                CouponSchema::MIN_ORDER_AMOUNT => 20,
                CouponSchema::USAGE_LIMIT => 100,
                CouponSchema::IS_ACTIVE => true,
            ],
        );
    }
}
