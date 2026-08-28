<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Language\LanguageSchema;

class LanguageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Language::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            LanguageSchema::LOCALE => $this->faker->languageCode(),
            LanguageSchema::NAME => $this->faker->name,
            LanguageSchema::CODE => $this->faker->languageCode(),
            LanguageSchema::CODE3 => $this->faker->countryISOAlpha3(),
            LanguageSchema::IS_RTL => $this->faker->boolean,
            LanguageSchema::IS_ACTIVE => $this->faker->boolean,
            LanguageSchema::IS_DEFAULT => $this->faker->boolean,
        ];
    }
}
