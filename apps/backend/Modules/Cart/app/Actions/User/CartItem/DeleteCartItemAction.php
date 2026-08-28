<?php

namespace Modules\Cart\Actions\User\CartItem;

use Modules\Core\Contracts\Events\Cart\CartItemRemovedEvent;

class DeleteCartItemAction extends AbstractCartItemAction
{
    public function handle(string $id): bool
    {
        $record = $this->cartItem->query()->findOrFail($id);
        $deleted = $record->delete();

        if ($deleted) {
            event(CartItemRemovedEvent::fill($record->toArray()));
        }

        return $deleted;
    }
}
