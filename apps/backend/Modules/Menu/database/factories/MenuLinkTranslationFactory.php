<?php

namespace Modules\Menu\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Menu\Models\MenuLinkTranslation;
use Modules\Menu\Schemas\MenuLink\MenuLinkTranslationSchema;

class MenuLinkTranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = MenuLinkTranslation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            MenuLinkTranslationSchema::MENU_LINK_ID => null,
            MenuLinkTranslationSchema::LANGUAGE_ID => 1,
            MenuLinkTranslationSchema::TITLE => $this->faker->words(2, true),
            MenuLinkTranslationSchema::DESCRIPTION => $this->faker->sentence(),
        ];
    }
}
