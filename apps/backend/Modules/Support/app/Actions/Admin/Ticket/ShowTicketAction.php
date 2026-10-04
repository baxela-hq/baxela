<?php

namespace Modules\Support\Actions\Admin\Ticket;

use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Core\Contracts\Gateways\User\UserGatewayInterface;
use Modules\Support\Models\Ticket;
use Modules\Support\Schemas\Ticket\TicketSchema;

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

        return $ticket;
    }
}
