<?php

namespace Modules\Support\Actions\Admin\Ticket;

use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Core\Contracts\Gateways\User\UserGatewayInterface;
use Modules\Support\Models\Ticket;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;

class ShowTicketAction extends AbstractTicketAction
{
    public function __construct(
        protected Ticket $model,
        private readonly UserGatewayInterface $userGateway,
        private readonly AccessGatewayInterface $accessGateway,
    ) {
        parent::__construct($model);
    }

    public function handle(string $id): Ticket
    {
        $ticket = $this->model->query()
            ->with(TicketSchema::RES_MESSAGES)
            ->findOrFail($id);

        $userId = $ticket->{TicketSchema::USER_ID};

        $ticket->setAttribute(
            TicketSchema::RES_CUSTOMER_NAME,
            $this->userGateway->getUserNamesByIds([$userId])[$userId] ?? null
        );

        $ticket->setAttribute(
            TicketSchema::RES_CUSTOMER_EMAIL,
            $this->accessGateway->getUserEmailsByIds([$userId])[$userId] ?? null
        );

        // Identify who wrote each reply: staff messages carry their author's
        // name (email fallback) so the admin thread shows which staff member
        // answered; customer messages just resolve back to the ticket owner.
        $authorIds = $ticket->{TicketSchema::RES_MESSAGES}
            ->pluck(TicketMessageSchema::USER_ID)
            ->unique()
            ->values()
            ->all();

        $names = $this->userGateway->getUserNamesByIds($authorIds);
        $emails = $this->accessGateway->getUserEmailsByIds($authorIds);

        $ticket->{TicketSchema::RES_MESSAGES}->each(function ($message) use ($names, $emails): void {
            $authorId = $message->{TicketMessageSchema::USER_ID};

            $message->setAttribute(
                TicketMessageSchema::RES_AUTHOR_NAME,
                $names[$authorId] ?? null
            );

            $message->setAttribute(
                TicketMessageSchema::RES_AUTHOR_EMAIL,
                $emails[$authorId] ?? null
            );
        });

        return $ticket;
    }
}
