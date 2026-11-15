<?php

namespace Modules\Discount\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Schemas\Redemption\RedemptionSchema;

/** @extends Factory<Redemption> */
class RedemptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Redemption::class;

    /**
     * Define the model's default state.
     */
    /** @param  array<string, mixed>  $params */
    public function definition(array $params = []): array
    {
        return [
            RedemptionSchema::COUPON_ID => $params[RedemptionSchema::COUPON_ID] ?? 1,
            RedemptionSchema::ORDER_ID => $params[RedemptionSchema::ORDER_ID] ?? 1,
            RedemptionSchema::USER_ID => $params[RedemptionSchema::USER_ID] ?? 1,
            RedemptionSchema::DISCOUNT_AMOUNT => $this->faker->randomFloat(2, 0, 9999),
        ];
    }
}
