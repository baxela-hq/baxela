<?php

namespace Modules\Audit\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Audit\Models\AuditLog;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;
use Modules\Core\Contracts\Events\AbstractBaseEvent;
use Modules\Core\Utils\Auth;
use Throwable;

class RecordAuditLogListener
{
    /**
     * Payload keys whose values must never reach the audit trail — event
     * payloads can carry live credentials such as OTP codes and passwords.
     * Mirrors the log redaction in Core's LogAllEvents.
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
     * Event class-name suffixes mapped to audit actions, longest first so
     * compound verbs like "StatusUpdated" win over "Updated".
     */
    protected const array ACTION_SUFFIXES = [
        'StatusUpdated' => 'status_updated',
        'EmailVerified' => 'email_verified',
        'SignedIn' => 'signed_in',
        'SignedUp' => 'signed_up',
        'CheckedOut' => 'checked_out',
        'Deactivated' => 'deactivated',
        'Unpublished' => 'unpublished',
        'Decreased' => 'decreased',
        'Initiated' => 'initiated',
        'Published' => 'published',
        'Activated' => 'activated',
        'Requested' => 'requested',
        'Delivered' => 'delivered',
        'Succeeded' => 'succeeded',
        'Completed' => 'completed',
        'Cancelled' => 'cancelled',
        'Refunded' => 'refunded',
        'Approved' => 'approved',
        'Depleted' => 'depleted',
        'Increased' => 'increased',
        'Created' => 'created',
        'Updated' => 'updated',
        'Deleted' => 'deleted',
        'Failed' => 'failed',
        'Shipped' => 'shipped',
        'Paid' => 'paid',
    ];

    /**
     * Handle the event.
     */
    public function handle(AbstractBaseEvent $event): void
    {
        try {
            $payload = $this->redact($event->toArray());

            AuditLog::query()->create([
                AuditLogSchema::USER_ID => Auth::id(),
                AuditLogSchema::MODULE => $this->moduleName($event),
                AuditLogSchema::ENTITY => $this->entityName($event),
                AuditLogSchema::ACTION => $this->actionName($event),
                AuditLogSchema::ENTITY_ID => $payload['id'] ?? null,
                AuditLogSchema::PROPERTIES => $payload,
                AuditLogSchema::IP_ADDRESS => request()?->ip(),
                AuditLogSchema::USER_AGENT => request()?->userAgent(),
            ]);
        } catch (Throwable $e) {
            // An audit failure must never break the request it is auditing.
            Log::error('Failed to record audit log', [
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Module name from the event contract namespace segment, e.g. events in
     * `Modules\Core\Contracts\Events\Menu\*` belong to the "menu" module.
     */
    protected function moduleName(AbstractBaseEvent $event): string
    {
        return Str::of($event::class)
            ->after('Modules\\Core\\Contracts\\Events\\')
            ->beforeLast('\\')
            ->lower()
            ->toString();
    }

    protected function entityName(AbstractBaseEvent $event): string
    {
        return Str::snake($this->stripVerb($event));
    }

    protected function actionName(AbstractBaseEvent $event): string
    {
        foreach (self::ACTION_SUFFIXES as $suffix => $action) {
            if (Str::endsWith($event->getClassName(), $suffix.'Event')) {
                return $action;
            }
        }

        return 'recorded';
    }

    /**
     * Class basename minus the "Event" suffix and the trailing verb, e.g.
     * "MenuLinkUpdatedEvent" → "MenuLink".
     */
    protected function stripVerb(AbstractBaseEvent $event): string
    {
        $name = Str::beforeLast($event->getClassName(), 'Event');

        foreach (self::ACTION_SUFFIXES as $suffix => $action) {
            if (Str::endsWith($name, $suffix)) {
                return Str::beforeLast($name, $suffix);
            }
        }

        return $name;
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
