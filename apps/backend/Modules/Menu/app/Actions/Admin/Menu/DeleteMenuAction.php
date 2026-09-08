<?php

namespace Modules\Menu\Actions\Admin\Menu;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Menu\MenuDeletedEvent;
use Modules\Menu\Exceptions\Menu\DeletionFailedException;
use Modules\Menu\Models\Menu;
use Throwable;

class DeleteMenuAction extends AbstractMenuAction
{
    /**
     * @throws DeletionFailedException|Throwable
     */
    public function handle(string $id): bool
    {
        $record = Menu::query()->findOrFail($id);

        try {
            DB::beginTransaction();

            $record->delete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new DeletionFailedException;
        }

        event(MenuDeletedEvent::fill(['id' => (int) $record->getKey()]));

        return true;
    }
}
