<?php

namespace Modules\Cart\Listeners\Auth\UserSignedIn;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Cart\Models\Cart;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Core\Contracts\Events\Auth\UserSignedInEvent;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;
use Throwable;

/**
 * Folds the guest cart carried by the sign-in request into the user's cart.
 * Runs synchronously inside the sign-in request so the storefront's first
 * authenticated cart fetch already sees the merged items.
 */
class MergeGuestCartListener
{
    public function handle(UserSignedInEvent $event): void
    {
        $token = $event->cart_token;
        if ($token === null || $token === '') {
            return;
        }

        try {
            DB::transaction(function () use ($event, $token) {
                // Row locks serialize concurrent/retried sign-ins sharing a
                // token; the loser finds the guest cart already gone and
                // no-ops, which also makes repeated merges idempotent.
                $guestCart = Cart::query()
                    ->where(CartSchema::TOKEN, $token)
                    ->lockForUpdate()
                    ->first();
                if ($guestCart === null) {
                    return;
                }

                $userCart = Cart::query()
                    ->where(CartSchema::USER_ID, $event->id)
                    ->lockForUpdate()
                    ->first();

                // The user has no cart yet — claim the guest cart outright;
                // its items and snapshots carry over untouched.
                if ($userCart === null) {
                    $guestCart->update([
                        CartSchema::USER_ID => $event->id,
                        CartSchema::TOKEN => null,
                    ]);

                    return;
                }

                $inventory = app(InventoryGatewayInterface::class);

                foreach ($guestCart->items()->get() as $guestItem) {
                    $variantId = $guestItem->{CartItemSchema::VARIANT_ID};
                    $existing = $userCart->items()
                        ->where(CartItemSchema::VARIANT_ID, $variantId)
                        ->first();

                    // The merged cart holds the maximum available quantity:
                    // guest + user amounts combined, capped by current stock.
                    $desired = $guestItem->{CartItemSchema::QUANTITY}
                        + (int) ($existing?->{CartItemSchema::QUANTITY} ?? 0);
                    $available = $inventory->availableQuantity((string) $variantId);
                    $final = $available === null ? 0 : min($desired, $available);

                    // Nothing stockable to add — skip the guest item and
                    // leave the user's cart untouched.
                    if ($final < 1) {
                        continue;
                    }

                    if ($existing !== null) {
                        $existing->update([CartItemSchema::QUANTITY => $final]);

                        continue;
                    }

                    $userCart->items()->create([
                        CartItemSchema::VARIANT_ID => $variantId,
                        CartItemSchema::QUANTITY => $final,
                        CartItemSchema::PRICE_SNAPSHOT => $guestItem->{CartItemSchema::PRICE_SNAPSHOT},
                        CartItemSchema::PRODUCT_NAME_SNAPSHOT => $guestItem->{CartItemSchema::PRODUCT_NAME_SNAPSHOT},
                    ]);
                }

                $guestCart->delete(); // items cascade
            });
        } catch (Throwable $exception) {
            // A failed merge must never break sign-in. The transaction rolled
            // back, so the guest cart survives intact — it just becomes
            // unreachable once the client drops its token.
            Log::warning('Guest cart merge failed', [
                'user_id' => $event->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
