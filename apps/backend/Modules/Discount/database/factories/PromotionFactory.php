<?php

namespace Modules\Discount\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Discount\Models\Promotion;
use Modules\Discount\Schemas\Promotion\PromotionSchema;
use Modules\Discount\Schemas\Promotion\ScopeTypeEnum;
use Modules\Discount\Schemas\Coupon\CouponTypeEnum;

class PromotionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Promotion::class;

    /**
     * Define the model's default state.
     */
    public function definition(array $params = []): array
    {
        $type = $params[PromotionSchema::TYPE] ?? CouponTypeEnum::PERCENT->value;

        return [
            PromotionSchema::NAME => $this->faker->words(3, true),
            PromotionSchema::SCOPE => ScopeTypeEnum::ALL->value,
            PromotionSchema::TYPE => $type,
            PromotionSchema::VALUE => $type === CouponTypeEnum::PERCENT->value
                ? $this->faker->randomFloat(2, 1, 50)
                : $this->faker->randomFloat(2, 1, 100),
            PromotionSchema::STARTS_AT => null,
            PromotionSchema::ENDS_AT => null,
            PromotionSchema::PRIORITY => 0,
            PromotionSchema::IS_ACTIVE => true,
        ];
    }
}
