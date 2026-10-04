<?php

namespace Modules\Support\Actions\User\Ticket;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Utils\Pagination;
use Modules\Support\Models\User\Ticket;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListTicketAction extends AbstractTicketAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = TicketSchema::TABLE.'.'.TicketSchema::ID;

        return QueryBuilder::for(Ticket::class)
            ->allowedFilters(
                AllowedFilter::exact(TicketSchema::STATUS),
            )
            ->allowedSorts(
                TicketSchema::ID,
                TicketSchema::STATUS,
                TicketSchema::LAST_MESSAGE_AT,
                TicketSchema::CREATED_AT,
            )
            ->select([
                $id,
                TicketSchema::SUBJECT,
                TicketSchema::ORDER_CODE,
                TicketSchema::STATUS,
                TicketSchema::LAST_MESSAGE_AT,
                TicketSchema::TABLE.'.'.TicketSchema::CREATED_AT,
                TicketSchema::TABLE.'.'.TicketSchema::UPDATED_AT,
            ])
            ->orderBy($id, 'desc')
            ->paginate(Pagination::perPage());
    }
}
