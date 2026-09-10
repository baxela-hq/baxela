<?php

namespace Modules\Cart\Actions\User\CartItem;

use Illuminate\Database\Eloquent\Model;
use Modules\Cart\Exceptions\User\CartItem\OutOfStockException;
use Modules\Cart\Http\Requests\User\CartItem\UpdateCartItemRequest;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;

class UpdateCartItemAction extends AbstractCartItemAction
{
    public function handle(string $id, UpdateCartItemRequest $request): Model
    {
        $record = $this->cartItem
            ->query()
            ->where(CartItemSchema::CART_ID, $this->getCartId())
            ->findOrFail($id);

        $quantity = (int) $request->input(CartItemSchema::QUANTITY);
        $available = app(InventoryGatewayInterface::class)
            ->availableQuantity((string) $record->{CartItemSchema::VARIANT_ID});
        if (is_null($available) || $available < $quantity) {
            throw new OutOfStockException(
                $this->variantDisplayName((int) $record->{CartItemSchema::VARIANT_ID}),
                $available ?? 0,
                (int) $record->{CartItemSchema::VARIANT_ID},
            );
        }

        $record->update($request->validated());

        return $record;
    }
}
