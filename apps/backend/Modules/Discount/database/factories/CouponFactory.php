<?php

namespace Modules\Discount\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Coupon\CouponTypeEnum;

class CouponFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Coupon::class;

    /**
     * Define the model's default state.
     */
    public function definition(array $params = []): array
    {
        $type = $params[CouponSchema::TYPE] ?? CouponTypeEnum::PERCENT->value;

        return [
            CouponSchema::CODE => strtoupper($this->faker->unique()->bothify('SAVE??')),
            CouponSchema::NAME => $this->faker->optional()->words(3, true),
            CouponSchema::TYPE => $type,
            CouponSchema::VALUE => $type === CouponTypeEnum::PERCENT->value
                ? $this->faker->randomFloat(2, 1, 50)
                : $this->faker->randomFloat(2, 1, 100),
            CouponSchema::MAX_DISCOUNT_AMOUNT => null,
            CouponSchema::MIN_ORDER_AMOUNT => null,
            CouponSchema::STARTS_AT => null,
            CouponSchema::ENDS_AT => null,
            CouponSchema::USAGE_LIMIT => null,
            CouponSchema::PER_USER_LIMIT => null,
            CouponSchema::USAGE_COUNT => 0,
            CouponSchema::IS_ACTIVE => true,
        ];
    }
}
