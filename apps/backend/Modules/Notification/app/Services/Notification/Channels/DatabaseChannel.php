<?php

namespace Modules\Notification\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\Gateways\Tenant\TenantGatewayInterface;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Notification\Events\NotificationCreated;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationSchema;
use Modules\Notification\Services\Notification\Contracts\MessageInterface;
use Modules\Notification\Services\Notification\Contracts\NotificationChannelInterface;
use Modules\Notification\Services\Notification\DTOs\Messages\DatabaseMessage;
use Modules\Notification\Services\Notification\DTOs\SendResult;

class DatabaseChannel implements NotificationChannelInterface
{
    public function __construct(private readonly TenantGatewayInterface $tenants) {}

    public function send(MessageInterface $message): SendResult
    {
        if (! $message instanceof DatabaseMessage) {
            throw new \InvalidArgumentException('Invalid message type');
        }

        $context = app(TenantContext::class);

        if ($context->tenantId() === null) {
            // System alerts fire in a tenant-neutral worker/console. The
            // notification table is store-owned, so write the platform
            // alert into the installation's default store rather than
            // letting the model's fail-closed guard drop the recipient.
            $default = $this->tenants->defaultTenant();

            if ($default !== null) {
                return $context->runForTenant($default->id, fn (): SendResult => $this->sendInTenant($message));
            }
        }

        return $this->sendInTenant($message);
    }

    private function sendInTenant(DatabaseMessage $message): SendResult
    {
        $ids = [];
        foreach ($message->recipients as $recipient) {
            $record = Notification::query()->create([
                NotificationSchema::USER_ID => $recipient,
                NotificationSchema::CODE => $message->code,
                NotificationSchema::AUDIENCE => $message->audience,
                NotificationSchema::TITLE => $message->title,
                NotificationSchema::BODY => $message->body,
                NotificationSchema::META => empty($message->meta) ? null : $message->meta,
            ]);
            $ids[] = $record->{NotificationSchema::ID};

            $this->broadcastCreated((int) $recipient, $message->audience, $record);
        }

        Log::info('Notification sent '.__CLASS__, $message->toArray());

        return new SendResult(success: true);
    }

    /**
     * Push the new row to the recipient's audience-scoped private
     * channel, in the same shape the list endpoints return. The badge
     * count must match those endpoints, so it filters by the row's
     * audience — a user id can hold both admin and storefront rows, and
     * only the event's own audience belongs on that app's bell. The
     * broadcast is queued like any other ShouldBroadcast event, so a
     * down websocket server never blocks the insert.
     */
    private function broadcastCreated(int $recipientId, string $audience, Notification $record): void
    {
        $code = $record->{NotificationSchema::CODE};

        NotificationCreated::dispatch(
            tenantId: (int) $record->{TenantColumn::TENANT_ID},
            notifiableId: $recipientId,
            audience: $audience,
            payload: [
                NotificationSchema::ID => $record->{NotificationSchema::ID},
                NotificationSchema::CODE => $code->value,
                NotificationSchema::TITLE => $record->{NotificationSchema::TITLE},
                NotificationSchema::BODY => $record->{NotificationSchema::BODY},
                NotificationSchema::META => $record->{NotificationSchema::META},
                NotificationSchema::READ_AT => $record->{NotificationSchema::READ_AT},
                NotificationSchema::CREATED_AT => $record->{NotificationSchema::CREATED_AT},
            ],
            unreadCount: Notification::query()
                ->where(NotificationSchema::USER_ID, $recipientId)
                ->where(NotificationSchema::AUDIENCE, $audience)
                ->whereNull(NotificationSchema::READ_AT)
                ->count(),
        );
    }
}
