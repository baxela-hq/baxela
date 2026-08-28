<?php

namespace Modules\Setting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Setting\Models\Setting;
use Modules\Setting\Models\Translation;
use Modules\Setting\Schemas\Translation\TranslationSchema;

class TranslationFactory extends Factory
{
    protected $model = Translation::class;

    /**
     * Define the model's default state.
     */
    public function definition(array $params = []): array
    {
        return [
            TranslationSchema::SETTING_ID => $params[TranslationSchema::SETTING_ID] ?? Setting::query()->inRandomOrder()->first(),
            TranslationSchema::LANGUAGE_ID => $params[TranslationSchema::LANGUAGE_ID] ?? $this->faker->numberBetween(1, 2),
            TranslationSchema::VALUE => $this->faker->word(),
        ];
    }
}
