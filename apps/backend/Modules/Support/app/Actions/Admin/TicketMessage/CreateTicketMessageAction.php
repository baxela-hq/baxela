<?php

namespace Modules\Support\Actions\Admin\TicketMessage;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Support\TicketMessageCreatedEvent;
use Modules\Core\Utils\Auth;
use Modules\Core\Utils\Locale;
use Modules\Support\Exceptions\TicketMessage\CreationFailedException;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Schemas\Ticket\TicketStatusEnum;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;
use Modules\Support\Schemas\TicketMessage\TicketSenderEnum;
use Throwable;

class CreateTicketMessageAction
{
    public function __construct(protected Ticket $model) {}

    /**
     * A staff reply marks the ticket as answered for the customer.
     *
     * @throws CreationFailedException|Throwable
     */
    public function handle(string $ticketId, array $data): TicketMessage
    {
        $ticket = $this->model->query()->findOrFail($ticketId);

        $staffId = (int) Auth::id();

        try {
            DB::beginTransaction();

            $message = TicketMessage::query()->create([
                TicketMessageSchema::TICKET_ID => $ticket->getKey(),
                TicketMessageSchema::USER_ID => $staffId,
                TicketMessageSchema::SENDER => TicketSenderEnum::ADMIN->value,
                TicketMessageSchema::BODY => $data[TicketMessageSchema::BODY],
            ]);

            $ticket->update([
                TicketSchema::STATUS => TicketStatusEnum::ANSWERED->value,
                TicketSchema::LAST_MESSAGE_AT => now(),
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        event(TicketMessageCreatedEvent::fill([
            'id' => (int) $message->getKey(),
            'ticket_id' => (int) $ticket->getKey(),
            'user_id' => (int) $ticket->{TicketSchema::USER_ID},
            'sender_user_id' => $staffId,
            'sender' => TicketSenderEnum::ADMIN->value,
            'subject' => $ticket->{TicketSchema::SUBJECT},
            'order_code' => $ticket->{TicketSchema::ORDER_CODE},
            'body' => $message->{TicketMessageSchema::BODY},
            'locale' => Locale::fromRequest(),
        ]));

        return $message;
    }
}
