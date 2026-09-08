<?php

namespace Modules\Menu\Actions\Admin\MenuLink;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Menu\MenuLinkCreatedEvent;
use Modules\Menu\Exceptions\MenuLink\CreationFailedException;
use Modules\Menu\Models\Menu;
use Modules\Menu\Models\MenuLink;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Throwable;

class CreateMenuLinkAction extends AbstractMenuLinkAction
{
    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(string $menuId, array $data): MenuLink
    {
        Menu::query()->findOrFail($menuId);

        try {
            DB::beginTransaction();

            $record = MenuLink::query()->create([
                MenuLinkSchema::MENU_ID => (int) $menuId,
                MenuLinkSchema::PARENT_ID => $data[MenuLinkSchema::PARENT_ID],
                MenuLinkSchema::POSITION => $data[MenuLinkSchema::POSITION],
                MenuLinkSchema::URL => $data[MenuLinkSchema::URL],
                MenuLinkSchema::TARGET => $data[MenuLinkSchema::TARGET],
            ]);

            foreach ($data[MenuLinkSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        event(MenuLinkCreatedEvent::fill($record->toArray()));

        return $record->load(MenuLinkSchema::RES_TRANSLATIONS);
    }
}
