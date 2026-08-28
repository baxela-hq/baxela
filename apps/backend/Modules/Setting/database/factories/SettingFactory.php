<?php

namespace Modules\Setting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingGroupEnum;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Schemas\Setting\SettingTypeEnum;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /**
     * Define the model's default state.
     */
    public function definition(array $params = []): array
    {
        return [
            SettingSchema::GROUP => $this->faker->randomElement(SettingGroupEnum::cases()),
            SettingSchema::TYPE => $this->faker->randomElement(SettingTypeEnum::cases()),
            SettingSchema::NAME => $params[SettingSchema::NAME] ?? $this->faker->randomElement(SettingNameEnum::cases()),
            SettingSchema::VALUE => $this->faker->word(),
            SettingSchema::IS_TRANSLATABLE => $this->faker->boolean(),
            SettingSchema::COMMENT => null,
        ];
    }
}
