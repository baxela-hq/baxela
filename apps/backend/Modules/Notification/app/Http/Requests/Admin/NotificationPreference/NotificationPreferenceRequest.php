<?php

namespace Modules\Notification\Http\Requests\Admin\NotificationPreference;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Notification\Schemas\Notification\NotificationAudienceEnum;
use Modules\Notification\Schemas\Notification\NotificationChannelEnum;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\Notification\NotificationPreferenceSchema;
use Modules\Notification\Support\PreferenceMatrix;

class NotificationPreferenceRequest extends FormRequest
{
    /**
     * Single-toggle payload for the preference update endpoint: the code
     * must be one of the admin-configurable notifications and the channel
     * one that code actually uses (per the config matrix).
     */
    public function rules(): array
    {
        $configured = PreferenceMatrix::configured(NotificationAudienceEnum::ADMIN);
        $code = (string) $this->input(NotificationPreferenceSchema::CODE);

        return [
            NotificationPreferenceSchema::CODE => [
                'required',
                new Enum(NotificationCodeEnum::class),
                Rule::in(array_keys($configured)),
            ],
            NotificationPreferenceSchema::CHANNEL => [
                'required',
                new Enum(NotificationChannelEnum::class),
                Rule::in($configured[$code] ?? []),
            ],
            NotificationPreferenceSchema::IS_ENABLED => ['required', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
