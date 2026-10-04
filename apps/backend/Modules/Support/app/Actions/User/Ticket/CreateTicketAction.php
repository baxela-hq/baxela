<?php

namespace Modules\Support\Actions\User\Ticket;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Support\TicketCreatedEvent;
use Modules\Core\Contracts\Events\Support\TicketMessageCreatedEvent;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Core\Utils\Auth;
use Modules\Core\Utils\Locale;
use Modules\Support\Exceptions\Ticket\CreationFailedException;
use Modules\Support\Exceptions\Ticket\InvalidOrderException;
use Modules\Support\Models\TicketMessage;
use Modules\Support\Models\User\Ticket;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Schemas\Ticket\TicketStatusEnum;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;
use Modules\Support\Schemas\TicketMessage\TicketSenderEnum;
use Throwable;

class CreateTicketAction extends AbstractTicketAction
{
    public function __construct(protected Ticket $model, private readonly OrderGatewayInterface $orderGateway)
    {
        parent::__construct($model);
    }

    /**
     * @throws CreationFailedException|InvalidOrderException|Throwable
     */
    public function handle(array $data): Ticket
    {
        $orderCode = $data[TicketSchema::ORDER_CODE] ?? null;
        $userId = (int) Auth::id();

        // an order link is optional, but must reference one of the
        // customer's own orders — never someone else's code
        if ($orderCode !== null && $this->orderGateway->getOrder($orderCode, (string) $userId) === null) {
            throw new InvalidOrderException;
        }

        try {
            DB::beginTransaction();

            $ticket = $this->model->query()->create([
                TicketSchema::ORDER_CODE => $orderCode,
                TicketSchema::SUBJECT => $data[TicketSchema::SUBJECT],
                TicketSchema::STATUS => TicketStatusEnum::OPEN->value,
                TicketSchema::LAST_MESSAGE_AT => now(),
            ]);

            $message = TicketMessage::query()->create([
                TicketMessageSchema::TICKET_ID => $ticket->getKey(),
                TicketMessageSchema::USER_ID => $userId,
                TicketMessageSchema::SENDER => TicketSenderEnum::CUSTOMER->value,
                TicketMessageSchema::BODY => $data[TicketMessageSchema::BODY],
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        $eventPayload = [
            'user_id' => $userId,
            'subject' => $ticket->{TicketSchema::SUBJECT},
            'order_code' => $orderCode,
            'locale' => Locale::fromRequest(),
        ];

        event(TicketCreatedEvent::fill([
            ...$eventPayload,
            'id' => (int) $ticket->getKey(),
            'status' => TicketStatusEnum::OPEN->value,
            'body' => $message->{TicketMessageSchema::BODY},
        ]));

        event(TicketMessageCreatedEvent::fill([
            ...$eventPayload,
            'id' => (int) $message->getKey(),
            'ticket_id' => (int) $ticket->getKey(),
            'sender_user_id' => $userId,
            'sender' => TicketSenderEnum::CUSTOMER->value,
            'body' => $message->{TicketMessageSchema::BODY},
        ]));

        return $ticket;
    }
}
