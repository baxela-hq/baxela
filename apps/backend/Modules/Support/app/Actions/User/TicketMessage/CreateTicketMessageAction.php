<?php

namespace Modules\Support\Actions\User\TicketMessage;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Support\TicketMessageCreatedEvent;
use Modules\Core\Utils\Auth;
use Modules\Core\Utils\Locale;
use Modules\Support\Exceptions\TicketMessage\CreationFailedException;
use Modules\Support\Models\TicketMessage;
use Modules\Support\Models\User\Ticket;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Schemas\Ticket\TicketStatusEnum;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;
use Modules\Support\Schemas\TicketMessage\TicketSenderEnum;
use Throwable;

class CreateTicketMessageAction
{
    public function __construct(protected Ticket $model) {}

    /**
     * A customer reply always reopens a closed ticket so the
     * conversation continues without staff intervention.
     *
     * @throws CreationFailedException|Throwable
     */
    public function handle(string $ticketId, array $data): TicketMessage
    {
        // the ownership scope turns someone else's ticket into a 404
        $ticket = $this->model->query()->findOrFail($ticketId);

        $userId = (int) Auth::id();

        try {
            DB::beginTransaction();

            $message = TicketMessage::query()->create([
                TicketMessageSchema::TICKET_ID => $ticket->getKey(),
                TicketMessageSchema::USER_ID => $userId,
                TicketMessageSchema::SENDER => TicketSenderEnum::CUSTOMER->value,
                TicketMessageSchema::BODY => $data[TicketMessageSchema::BODY],
            ]);

            $ticket->update([
                TicketSchema::STATUS => TicketStatusEnum::OPEN->value,
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
            'sender_user_id' => $userId,
            'sender' => TicketSenderEnum::CUSTOMER->value,
            'subject' => $ticket->{TicketSchema::SUBJECT},
            'order_code' => $ticket->{TicketSchema::ORDER_CODE},
            'body' => $message->{TicketMessageSchema::BODY},
            'locale' => Locale::fromRequest(),
        ]));

        return $message;
    }
}
