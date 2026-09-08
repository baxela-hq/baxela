<?php

namespace Modules\Menu\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Menu\Models\MenuTranslation;
use Modules\Menu\Schemas\Menu\MenuTranslationSchema;

class MenuTranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = MenuTranslation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            MenuTranslationSchema::MENU_ID => null,
            MenuTranslationSchema::LANGUAGE_ID => 1,
            MenuTranslationSchema::TITLE => $this->faker->words(2, true),
            MenuTranslationSchema::DESCRIPTION => $this->faker->sentence(),
        ];
    }
}
