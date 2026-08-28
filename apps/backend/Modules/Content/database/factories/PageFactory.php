<?php

namespace Modules\Content\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Content\Models\Page;
use Modules\Content\Models\PageTranslation;
use Modules\Content\Schemas\Page\PageSchema;
use Modules\Content\Schemas\Page\PageStatusEnum;

class PageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Page::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            PageSchema::STATUS => $this->faker->randomElement(PageStatusEnum::cases()),
        ];
    }

    public function withTranslations(int $count = 1): self
    {
        return $this->has(
            PageTranslation::factory()->count($count),
            'translations'
        );
    }
}
