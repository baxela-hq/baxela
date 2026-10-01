<?php

namespace Modules\Core\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\Events\AbstractBaseEvent;

class LogAllEvents
{
    /**
     * Payload keys whose values must never reach the logs — event payloads
     * carry live credentials such as OTP codes and passwords.
     */
    protected const array REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'api_key',
        'secret',
        'code',
        'credit_card',
    ];

    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        if (config('core.log_requests') && $event instanceof AbstractBaseEvent) {
            Log::info('Event Fired', [
                'event' => get_class($event),
                'payload' => $this->redact($event->toArray()),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function redact(array $payload): array
    {
        return collect($payload)
            ->map(fn ($value, $key) => in_array(strtolower((string) $key), self::REDACTED_KEYS, true)
                ? '[redacted]'
                : $value)
            ->all();
    }
}
