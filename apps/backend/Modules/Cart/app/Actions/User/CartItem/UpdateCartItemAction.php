<?php

namespace Modules\Cart\Actions\User\CartItem;

use Illuminate\Database\Eloquent\Model;
use Modules\Cart\Http\Requests\User\CartItem\UpdateCartItemRequest;
use Modules\Cart\Schemas\CartItem\CartItemSchema;

class UpdateCartItemAction extends AbstractCartItemAction
{
    public function handle(string $id, UpdateCartItemRequest $request): Model
    {
        $record = $this->cartItem
            ->query()
            ->where(CartItemSchema::CART_ID, $this->getCartId())
            ->findOrFail($id);
        $record->update($request->validated());

        return $record;
    }
}
