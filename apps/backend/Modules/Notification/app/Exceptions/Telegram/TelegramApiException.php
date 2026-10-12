<?php

namespace Modules\Notification\Exceptions\Telegram;

use Modules\Notification\Exceptions\ErrorCodeEnum;
use Modules\Notification\Exceptions\NotificationException;
use Throwable;

/**
 * A Telegram Bot API call failed. Carries the API's own error_code in
 * meta (400 chat not found, 403 blocked by the user, 429 throttled, …)
 * so callers can react — e.g. the channel drops the subscription on a
 * permanent 400/403 instead of retrying forever.
 */
class TelegramApiException extends NotificationException
{
    public const string META_ERROR_CODE = 'error_code';

    public const string META_DESCRIPTION = 'description';

    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::TELEGRAM_REQUEST_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }

    public static function requestFailed(int $errorCode, string $description): static
    {
        return new static(meta: [
            self::META_ERROR_CODE => $errorCode,
            self::META_DESCRIPTION => $description,
        ]);
    }

    /**
     * Telegram's own error code (not HTTP): 400 bad request / chat not
     * found, 403 blocked, 429 flood control.
     */
    public function telegramErrorCode(): int
    {
        return (int) ($this->meta[self::META_ERROR_CODE] ?? 0);
    }

    /**
     * The chat can never receive messages again (user blocked the bot or
     * the chat is gone) — callers should drop the subscription.
     */
    public function chatIsGone(): bool
    {
        return in_array($this->telegramErrorCode(), [400, 403], true);
    }

    public function description(): string
    {
        return (string) ($this->meta[self::META_DESCRIPTION] ?? '');
    }
}
