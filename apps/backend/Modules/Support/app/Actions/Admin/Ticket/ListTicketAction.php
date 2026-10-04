<?php

namespace Modules\Support\Actions\Admin\Ticket;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Contracts\Gateways\User\UserGatewayInterface;
use Modules\Core\Utils\Pagination;
use Modules\Support\Models\Ticket;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListTicketAction extends AbstractTicketAction
{
    public function __construct(protected Ticket $model, private readonly UserGatewayInterface $userGateway)
    {
        parent::__construct($model);
    }

    public function handle(): LengthAwarePaginator
    {
        $id = TicketSchema::TABLE.'.'.TicketSchema::ID;

        $paginator = QueryBuilder::for(Ticket::class)
            ->allowedFilters(
                AllowedFilter::exact(TicketSchema::STATUS),
                AllowedFilter::exact(TicketSchema::USER_ID),
                AllowedFilter::partial(TicketSchema::SUBJECT),
                AllowedFilter::partial(TicketSchema::ORDER_CODE),
            )
            ->allowedSorts(
                TicketSchema::ID,
                TicketSchema::STATUS,
                TicketSchema::LAST_MESSAGE_AT,
                TicketSchema::CREATED_AT,
            )
            ->select([
                $id,
                TicketSchema::USER_ID,
                TicketSchema::SUBJECT,
                TicketSchema::ORDER_CODE,
                TicketSchema::STATUS,
                TicketSchema::LAST_MESSAGE_AT,
                TicketSchema::TABLE.'.'.TicketSchema::CREATED_AT,
                TicketSchema::TABLE.'.'.TicketSchema::UPDATED_AT,
            ])
            ->orderBy($id, 'desc')
            ->paginate(Pagination::perPage());

        // customer display names are resolved in bulk through the user
        // gateway and stashed on each record for the transformer
        $names = $this->userGateway->getUserNamesByIds(
            $paginator->getCollection()->pluck(TicketSchema::USER_ID)->unique()->values()->all()
        );

        $paginator->getCollection()->each(
            fn (Ticket $ticket) => $ticket->setAttribute(
                TicketSchema::RES_CUSTOMER_NAME,
                $names[$ticket->{TicketSchema::USER_ID}] ?? null
            )
        );

        return $paginator;
    }
}
