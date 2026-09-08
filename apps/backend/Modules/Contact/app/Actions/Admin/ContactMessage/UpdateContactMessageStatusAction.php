<?php

namespace Modules\Contact\Actions\Admin\ContactMessage;

use Illuminate\Support\Facades\DB;
use Modules\Contact\Exceptions\ContactMessage\StatusUpdateFailedException;
use Modules\Contact\Models\ContactMessage;
use Modules\Contact\Schemas\ContactMessage\ContactMessageSchema;
use Modules\Core\Contracts\Events\Contact\ContactMessageStatusUpdatedEvent;
use Throwable;

class UpdateContactMessageStatusAction extends AbstractContactMessageAction
{
    /**
     * @throws StatusUpdateFailedException|Throwable
     */
    public function handle(string $id, array $data): ContactMessage
    {
        $record = ContactMessage::query()->findOrFail($id);

        try {
            DB::beginTransaction();

            $record->update([
                ContactMessageSchema::STATUS => $data[ContactMessageSchema::STATUS],
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new StatusUpdateFailedException;
        }

        event(ContactMessageStatusUpdatedEvent::fill([
            ContactMessageSchema::ID => (int) $record->getKey(),
            ContactMessageSchema::STATUS => $data[ContactMessageSchema::STATUS],
        ]));

        return $record;
    }
}
