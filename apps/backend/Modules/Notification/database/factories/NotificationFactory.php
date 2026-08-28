<?php

namespace Modules\Notification\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationAudienceEnum;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\Notification\NotificationSchema;

class NotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            NotificationSchema::USER_ID => $this->faker->randomDigitNotZero(),
            NotificationSchema::CODE => $this->faker->randomElement(NotificationCodeEnum::cases()),
            NotificationSchema::AUDIENCE => $this->faker->randomElement(NotificationAudienceEnum::cases()),
            NotificationSchema::TITLE => $this->faker->word(2),
            NotificationSchema::BODY => $this->faker->paragraph(),
            NotificationSchema::META => null,
            NotificationSchema::READ_AT => null,
        ];
    }
}
