<?php

namespace Modules\Cart\Actions\User\CartItem;

use Illuminate\Database\Eloquent\Model;
use Modules\Cart\Http\Requests\User\CartItem\UpdateCartItemRequest;

class UpdateCartItemAction extends AbstractCartItemAction
{
    public function handle(string $id, UpdateCartItemRequest $request): Model
    {
        $record = $this->cartItem->findOrFail($id);
        $record->update($request->validated());

        return $record;
    }
}
