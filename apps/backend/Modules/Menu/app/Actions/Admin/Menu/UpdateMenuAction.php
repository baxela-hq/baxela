<?php

namespace Modules\Menu\Actions\Admin\Menu;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Menu\MenuUpdatedEvent;
use Modules\Menu\Exceptions\Menu\UpdateFailedException;
use Modules\Menu\Models\Menu;
use Modules\Menu\Schemas\Menu\MenuSchema;
use Throwable;

class UpdateMenuAction extends AbstractMenuAction
{
    /**
     * @throws UpdateFailedException|Throwable
     */
    public function handle(string $id, array $data): Menu
    {
        $record = Menu::query()->findOrFail($id);

        try {
            DB::beginTransaction();

            $record->update([
                MenuSchema::LOCATION => $data[MenuSchema::LOCATION],
                MenuSchema::IS_ACTIVE => $data[MenuSchema::IS_ACTIVE],
            ]);

            $record->translations()->delete();
            foreach ($data[MenuSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new UpdateFailedException;
        }

        event(MenuUpdatedEvent::fill($record->toArray()));

        return $record->load(MenuSchema::RES_TRANSLATIONS);
    }
}
