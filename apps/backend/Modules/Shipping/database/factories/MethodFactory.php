<?php

namespace Modules\Shipping\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Schemas\Method\MethodSchema;

/** @extends Factory<Method> */
class MethodFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Method::class;

    /**
     * Define the model's default state.
     */
    /** @param  array<string, mixed>  $params */
    public function definition(array $params = []): array
    {
        return [
            MethodSchema::CODE => $params[MethodSchema::CODE] ?? $this->faker->unique()->lexify('method-????'),
            MethodSchema::IS_ACTIVE => $this->faker->boolean(),
            MethodSchema::POSITION => $this->faker->numberBetween(1, 20),
        ];
    }
}
