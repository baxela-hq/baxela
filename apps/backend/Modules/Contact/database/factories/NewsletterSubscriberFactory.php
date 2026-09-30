<?php

namespace Modules\Contact\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contact\Models\NewsletterSubscriber;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberSchema;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberStatusEnum;

class NewsletterSubscriberFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = NewsletterSubscriber::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            NewsletterSubscriberSchema::EMAIL => $this->faker->unique()->safeEmail(),
            NewsletterSubscriberSchema::LOCALE => $this->faker->randomElement(['en', 'fa']),
            NewsletterSubscriberSchema::STATUS => NewsletterSubscriberStatusEnum::SUBSCRIBED->value,
        ];
    }
}
