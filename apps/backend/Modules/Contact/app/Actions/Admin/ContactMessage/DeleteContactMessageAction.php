<?php

namespace Modules\Contact\Actions\Admin\ContactMessage;

use Illuminate\Support\Facades\DB;
use Modules\Contact\Exceptions\ContactMessage\DeletionFailedException;
use Modules\Contact\Models\ContactMessage;
use Modules\Core\Contracts\Events\Contact\ContactMessageDeletedEvent;
use Throwable;

class DeleteContactMessageAction extends AbstractContactMessageAction
{
    /**
     * @throws DeletionFailedException|Throwable
     */
    public function handle(string $id): bool
    {
        $record = ContactMessage::query()->findOrFail($id);

        try {
            DB::beginTransaction();

            $record->delete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new DeletionFailedException;
        }

        event(ContactMessageDeletedEvent::fill(['id' => (int) $record->getKey()]));

        return true;
    }
}
