<?php

namespace Modules\Menu\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Menu\Models\MenuLink;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkTargetEnum;

class MenuLinkFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = MenuLink::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            MenuLinkSchema::MENU_ID => null,
            MenuLinkSchema::PARENT_ID => null,
            MenuLinkSchema::POSITION => $this->faker->numberBetween(1, 255),
            MenuLinkSchema::URL => $this->faker->url(),
            MenuLinkSchema::TARGET => MenuLinkTargetEnum::SELF->value,
        ];
    }
}
