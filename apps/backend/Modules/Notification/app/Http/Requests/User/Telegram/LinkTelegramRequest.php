<?php

namespace Modules\Notification\Http\Requests\User\Telegram;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Notification\Schemas\TelegramSubscription\TelegramSubscriptionSchema;

class LinkTelegramRequest extends FormRequest
{
    /**
     * Linking accepts either credential — exactly one is filled: the
     * 6-digit code from the bot's /start reply, or the numeric chat id
     * reported by the bot (direct entry). Both keys always ride along;
     * the unused one is null.
     */
    public function rules(): array
    {
        return [
            TelegramSubscriptionSchema::CODE => [
                'nullable', 'string', 'digits:6',
                'required_without:'.TelegramSubscriptionSchema::CHAT_ID,
            ],
            TelegramSubscriptionSchema::CHAT_ID => [
                'nullable', 'integer',
                'required_without:'.TelegramSubscriptionSchema::CODE,
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
