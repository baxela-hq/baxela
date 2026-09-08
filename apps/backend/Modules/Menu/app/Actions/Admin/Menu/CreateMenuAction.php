<?php

namespace Modules\Menu\Actions\Admin\Menu;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Menu\MenuCreatedEvent;
use Modules\Menu\Exceptions\Menu\CreationFailedException;
use Modules\Menu\Models\Menu;
use Modules\Menu\Schemas\Menu\MenuSchema;
use Throwable;

class CreateMenuAction extends AbstractMenuAction
{
    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(array $data): Menu
    {
        try {
            DB::beginTransaction();

            $record = Menu::query()->create([
                MenuSchema::LOCATION => $data[MenuSchema::LOCATION],
                MenuSchema::IS_ACTIVE => $data[MenuSchema::IS_ACTIVE],
            ]);

            foreach ($data[MenuSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        event(MenuCreatedEvent::fill($record->toArray()));

        return $record->load(MenuSchema::RES_TRANSLATIONS);
    }
}
