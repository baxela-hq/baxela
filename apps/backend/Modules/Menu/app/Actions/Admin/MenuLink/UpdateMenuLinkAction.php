<?php

namespace Modules\Menu\Actions\Admin\MenuLink;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Menu\MenuLinkUpdatedEvent;
use Modules\Menu\Exceptions\MenuLink\CircularMoveException;
use Modules\Menu\Exceptions\MenuLink\UpdateFailedException;
use Modules\Menu\Models\MenuLink;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Throwable;

class UpdateMenuLinkAction extends AbstractMenuLinkAction
{
    /**
     * @throws CircularMoveException|UpdateFailedException|Throwable
     */
    public function handle(string $menuId, string $linkId, array $data): MenuLink
    {
        $record = MenuLink::query()
            ->where(MenuLinkSchema::MENU_ID, $menuId)
            ->findOrFail($linkId);

        if (array_key_exists(MenuLinkSchema::PARENT_ID, $data)) {
            $parentId = $data[MenuLinkSchema::PARENT_ID];

            if ($parentId !== null) {
                $this->ensureValidMove($record, (int) $parentId);
            }
        }

        try {
            DB::beginTransaction();

            $record->update([
                MenuLinkSchema::PARENT_ID => $data[MenuLinkSchema::PARENT_ID],
                MenuLinkSchema::POSITION => $data[MenuLinkSchema::POSITION],
                MenuLinkSchema::URL => $data[MenuLinkSchema::URL],
                MenuLinkSchema::TARGET => $data[MenuLinkSchema::TARGET],
            ]);

            $record->translations()->delete();
            foreach ($data[MenuLinkSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new UpdateFailedException;
        }

        event(MenuLinkUpdatedEvent::fill($record->toArray()));

        return $record->load(MenuLinkSchema::RES_TRANSLATIONS);
    }

    /**
     * @throws CircularMoveException
     */
    protected function ensureValidMove(MenuLink $link, int $parentId): void
    {
        $target = MenuLink::query()->findOrFail($parentId);

        if ($target->getKey() === $link->getKey()) {
            throw new CircularMoveException;
        }

        $ancestors = MenuLink::query()
            ->where(MenuLinkSchema::MENU_ID, $link->{MenuLinkSchema::MENU_ID})
            ->pluck(MenuLinkSchema::PARENT_ID, MenuLinkSchema::ID);

        $cursor = $target->getKey();

        while ($cursor !== null) {
            if ((int) $cursor === (int) $link->getKey()) {
                throw new CircularMoveException;
            }

            $cursor = $ancestors instanceof Collection
                ? ($ancestors->has($cursor) ? $ancestors->get($cursor) : null)
                : ($ancestors[$cursor] ?? null);
        }
    }
}
