<?php

namespace Modules\Notification\Exceptions;

use Modules\Core\Exceptions\BaseException;

class NotificationException extends BaseException
{
    public static function telegramUnconfigured(): static
    {
        return new static(ErrorCodeEnum::TELEGRAM_UNCONFIGURED->value, 400);
    }

    public static function telegramLinkInvalid(): static
    {
        return new static(ErrorCodeEnum::TELEGRAM_LINK_INVALID->value, 400);
    }

    public static function telegramChatUnreachable(): static
    {
        return new static(ErrorCodeEnum::TELEGRAM_CHAT_UNREACHABLE->value, 400);
    }

    public static function telegramNotLinked(): static
    {
        return new static(ErrorCodeEnum::TELEGRAM_NOT_LINKED->value, 404);
    }

    public static function telegramWebhookUnauthorized(): static
    {
        return new static(ErrorCodeEnum::TELEGRAM_WEBHOOK_UNAUTHORIZED->value, 403);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function telegramRequestFailed(array $meta = []): static
    {
        return new static(ErrorCodeEnum::TELEGRAM_REQUEST_FAILED->value, 400, $meta);
    }
}
