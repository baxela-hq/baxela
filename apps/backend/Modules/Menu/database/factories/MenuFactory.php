<?php

namespace Modules\Menu\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Menu\Models\Menu;
use Modules\Menu\Schemas\Menu\MenuLocationEnum;
use Modules\Menu\Schemas\Menu\MenuSchema;

class MenuFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Menu::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            MenuSchema::LOCATION => $this->faker->unique()->randomElement(MenuLocationEnum::cases())->value,
            MenuSchema::IS_ACTIVE => $this->faker->boolean(),
        ];
    }
}
