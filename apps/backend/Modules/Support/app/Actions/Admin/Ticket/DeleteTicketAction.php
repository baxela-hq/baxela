<?php

namespace Modules\Support\Actions\Admin\Ticket;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Support\TicketDeletedEvent;
use Modules\Support\Exceptions\Ticket\DeletionFailedException;
use Modules\Support\Models\Ticket;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Throwable;

class DeleteTicketAction extends AbstractTicketAction
{
    /**
     * @throws DeletionFailedException|Throwable
     */
    public function handle(string $id): bool
    {
        $record = Ticket::query()->findOrFail($id);

        try {
            DB::beginTransaction();

            // no FK constraint by convention, so the conversation is
            // removed explicitly alongside the ticket
            $record->{TicketSchema::RES_MESSAGES}()->delete();
            $record->delete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new DeletionFailedException;
        }

        event(TicketDeletedEvent::fill(['id' => (int) $record->getKey()]));

        return true;
    }
}
