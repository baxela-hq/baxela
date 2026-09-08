<?php

namespace Modules\Menu\Actions\Admin\MenuLink;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Menu\MenuLinkDeletedEvent;
use Modules\Menu\Exceptions\MenuLink\DeletionFailedException;
use Modules\Menu\Models\MenuLink;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Throwable;

class DeleteMenuLinkAction extends AbstractMenuLinkAction
{
    /**
     * @throws DeletionFailedException|Throwable
     */
    public function handle(string $menuId, string $linkId): bool
    {
        $record = MenuLink::query()
            ->where(MenuLinkSchema::MENU_ID, $menuId)
            ->findOrFail($linkId);

        try {
            DB::beginTransaction();

            MenuLink::query()
                ->whereIn(MenuLinkSchema::ID, $this->subtreeIds($record))
                ->delete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new DeletionFailedException;
        }

        event(MenuLinkDeletedEvent::fill([
            'id' => (int) $record->getKey(),
            'menu_id' => (int) $record->{MenuLinkSchema::MENU_ID},
        ]));

        return true;
    }

    /**
     * Collect the link id together with every descendant id (adjacency walk).
     *
     * @return array<int, int>
     */
    private function subtreeIds(MenuLink $link): array
    {
        $ids = [(int) $link->getKey()];
        $cursor = [$link->getKey()];

        while ($cursor !== []) {
            $cursor = MenuLink::query()
                ->where(MenuLinkSchema::MENU_ID, $link->{MenuLinkSchema::MENU_ID})
                ->whereIn(MenuLinkSchema::PARENT_ID, $cursor)
                ->pluck(MenuLinkSchema::ID)
                ->all();

            $ids = array_merge($ids, $cursor);
        }

        return $ids;
    }
}
