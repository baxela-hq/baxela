<?php

namespace Modules\Order\Actions\Admin\OrderReturn;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Order\OrderReturnApprovedEvent;
use Modules\Core\Contracts\Events\Order\OrderReturnCancelledEvent;
use Modules\Core\Contracts\Events\Order\OrderReturnCompletedEvent;
use Modules\Core\Contracts\Events\Order\OrderReturnReceivedEvent;
use Modules\Core\Contracts\Events\Order\OrderReturnRejectedEvent;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;
use Modules\Core\Utils\Locale;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\OrderReturn;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnStatusEnum;
use Throwable;

class UpdateOrderReturnAction
{
    public function __construct(protected InventoryGatewayInterface $inventoryGateway) {}

    /**
     * Admin-side transition. `received` restores the returned stock when
     * requested — exactly once, guarded by stock_restored_at inside the
     * same transaction, so retries and concurrent admins cannot double
     * restore. `completed` never refunds automatically: the merchant
     * refunds through the payment ledger, then closes the return.
     *
     * @param  array{status: string, admin_note?: string|null, restore_stock?: bool}  $data
     *
     * @throws OrderException
     */
    public function handle(string $id, array $data): OrderReturn
    {
        $status = OrderReturnStatusEnum::from($data[OrderReturnSchema::STATUS]);
        $restoreStock = $data[OrderReturnSchema::REQ_RESTORE_STOCK] ?? true;

        $return = DB::transaction(function () use ($id, $status, $data, $restoreStock): OrderReturn {
            $return = OrderReturn::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if (! $return->{OrderReturnSchema::STATUS}->canTransitionTo($status)) {
                throw OrderException::returnInvalidTransition();
            }

            if (! is_null($data[OrderReturnSchema::ADMIN_NOTE] ?? null)) {
                $return->{OrderReturnSchema::ADMIN_NOTE} = $data[OrderReturnSchema::ADMIN_NOTE];
            }

            $stockRestored = false;

            // Restock happens only on the first successful entry into
            // `received` — the flag flips in this same transaction, so a
            // mid-loop failure rolls back both the status and the stock
            if ($status === OrderReturnStatusEnum::RECEIVED
                && $restoreStock
                && is_null($return->{OrderReturnSchema::STOCK_RESTORED_AT})) {
                $this->restoreStock($return);
                $return->{OrderReturnSchema::STOCK_RESTORED_AT} = now();
                $stockRestored = true;
            }

            $return->{OrderReturnSchema::STATUS} = $status;
            $return->save();

            if ($stockRestored) {
                $return->setAttribute('stockRestored', true);
            }

            return $return;
        });

        $return->load(OrderReturnSchema::RES_ORDER);

        $payload = [
            OrderReturnSchema::ID => (int) $return->{OrderReturnSchema::ID},
            OrderReturnSchema::ORDER_ID => (int) $return->{OrderReturnSchema::ORDER_ID},
            OrderSchema::ORDER_CODE => (string) $return->{OrderReturnSchema::RES_ORDER}?->{OrderSchema::ORDER_CODE},
            OrderSchema::USER_ID => (int) $return->{OrderReturnSchema::USER_ID},
            OrderReturnSchema::STATUS => $status->value,
            'locale' => Locale::fromRequest(),
        ];

        $event = match ($status) {
            OrderReturnStatusEnum::APPROVED => OrderReturnApprovedEvent::class,
            OrderReturnStatusEnum::REJECTED => OrderReturnRejectedEvent::class,
            OrderReturnStatusEnum::RECEIVED => OrderReturnReceivedEvent::class,
            OrderReturnStatusEnum::COMPLETED => OrderReturnCompletedEvent::class,
            OrderReturnStatusEnum::CANCELLED => OrderReturnCancelledEvent::class,
            default => null,
        };

        if (! is_null($event)) {
            $extra = $status === OrderReturnStatusEnum::RECEIVED
                ? ['stock_restored' => (bool) $return->getAttribute('stockRestored')]
                : [];

            event($event::fill($payload + $extra));
        }

        return $return->refresh();
    }

    /**
     * @throws Throwable mid-loop failure bubbles so the transaction rolls
     *                   back the status change with it
     */
    private function restoreStock(OrderReturn $return): void
    {
        $items = OrderItem::query()
            ->where(OrderItemSchema::ORDER_ID, $return->{OrderReturnSchema::ORDER_ID})
            ->get();

        foreach ($items as $item) {
            $this->inventoryGateway->restore(
                (string) $item->{OrderItemSchema::VARIANT_ID},
                (int) $item->{OrderItemSchema::QUANTITY},
            );
        }
    }
}
