<?php

namespace Modules\Support\Actions\User\Ticket;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Support\TicketStatusUpdatedEvent;
use Modules\Core\Utils\Locale;
use Modules\Support\Exceptions\Ticket\StatusUpdateFailedException;
use Modules\Support\Models\User\Ticket;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Throwable;

class UpdateTicketStatusAction extends AbstractTicketAction
{
    /**
     * Customers may only close or reopen their own ticket; the request
     * validation restricts the accepted values accordingly.
     *
     * @throws StatusUpdateFailedException|Throwable
     */
    public function handle(string $id, array $data): Ticket
    {
        $record = $this->model->query()->findOrFail($id);

        try {
            DB::beginTransaction();

            $record->update([
                TicketSchema::STATUS => $data[TicketSchema::STATUS],
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new StatusUpdateFailedException;
        }

        event(TicketStatusUpdatedEvent::fill([
            'id' => (int) $record->getKey(),
            'user_id' => (int) $record->{TicketSchema::USER_ID},
            'status' => $data[TicketSchema::STATUS],
            'locale' => Locale::fromRequest(),
        ]));

        return $record;
    }
}
