<?php

namespace Modules\Shipping\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shipping\Models\MethodTranslation;
use Modules\Shipping\Schemas\Method\MethodTranslationSchema;

class MethodTranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = MethodTranslation::class;

    /**
     * Define the model's default state.
     */
    public function definition(array $params = []): array
    {
        return [
            MethodTranslationSchema::METHOD_ID => $params[MethodTranslationSchema::METHOD_ID] ?? 1,
            MethodTranslationSchema::LANGUAGE_ID => $params[MethodTranslationSchema::LANGUAGE_ID] ?? 1,
            MethodTranslationSchema::NAME => $this->faker->words(asText: true),
            MethodTranslationSchema::DESCRIPTION => $this->faker->sentence(),
        ];
    }
}
