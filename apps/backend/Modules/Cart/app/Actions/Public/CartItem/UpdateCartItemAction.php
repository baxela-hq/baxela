<?php

namespace Modules\Cart\Actions\Public\CartItem;

use Illuminate\Database\Eloquent\Model;
use Modules\Cart\Exceptions\User\CartItem\OutOfStockException;
use Modules\Cart\Http\Requests\Public\CartItem\UpdateCartItemRequest;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Cart\Support\VariantDisplayName;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;

class UpdateCartItemAction extends AbstractCartItemAction
{
    public function handle(string $token, string $id, UpdateCartItemRequest $request): Model
    {
        // A cart that was never created has no items to update — the scoped
        // findOrFail turns that into the same 404 as an unknown item id.
        $cartId = $this->findCartId($token) ?? 0;

        $record = $this->cartItem
            ->query()
            ->where(CartItemSchema::CART_ID, $cartId)
            ->findOrFail($id);

        $quantity = (int) $request->input(CartItemSchema::QUANTITY);
        $available = app(InventoryGatewayInterface::class)
            ->availableQuantity((string) $record->{CartItemSchema::VARIANT_ID});
        if (is_null($available) || $available < $quantity) {
            throw new OutOfStockException(
                VariantDisplayName::for((int) $record->{CartItemSchema::VARIANT_ID}),
                $available ?? 0,
                (int) $record->{CartItemSchema::VARIANT_ID},
            );
        }

        $record->update($request->validated());

        return $record;
    }
}
