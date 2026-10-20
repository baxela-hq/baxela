<?php

namespace Modules\Order\Actions\User\OrderReturn;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Order\OrderReturnCancelledEvent;
use Modules\Core\Contracts\Events\Order\OrderReturnShippedEvent;
use Modules\Core\Utils\Auth;
use Modules\Core\Utils\Locale;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\OrderReturn;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnStatusEnum;

/**
 * Customer-side transitions on their own return: mark the goods shipped
 * (optionally with a tracking number) or cancel before shipment.
 */
class UpdateOwnOrderReturnAction
{
    /**
     * @param  array{tracking_number?: string|null}  $data
     *
     * @throws OrderException
     */
    public function shipped(string $id, array $data): OrderReturn
    {
        $return = $this->transition($id, OrderReturnStatusEnum::SHIPPED, function (OrderReturn $return) use ($data): void {
            $return->{OrderReturnSchema::TRACKING_NUMBER} = $data[OrderReturnSchema::TRACKING_NUMBER] ?? null;
        });

        event(OrderReturnShippedEvent::fill($this->payload($return) + [
            OrderReturnSchema::TRACKING_NUMBER => $return->{OrderReturnSchema::TRACKING_NUMBER},
        ]));

        return $return;
    }

    /**
     * @throws OrderException
     */
    public function cancel(string $id): OrderReturn
    {
        $return = $this->transition($id, OrderReturnStatusEnum::CANCELLED);

        event(OrderReturnCancelledEvent::fill($this->payload($return)));

        return $return;
    }

    /**
     * @throws OrderException
     */
    private function transition(string $id, OrderReturnStatusEnum $status, ?callable $mutate = null): OrderReturn
    {
        return DB::transaction(function () use ($id, $status, $mutate): OrderReturn {
            $return = OrderReturn::query()
                ->whereKey($id)
                ->where(OrderReturnSchema::USER_ID, Auth::id())
                ->lockForUpdate()
                ->first();

            if (is_null($return)) {
                throw OrderException::returnNotFound();
            }

            if (! $return->{OrderReturnSchema::STATUS}->canTransitionTo($status)) {
                throw OrderException::returnInvalidTransition();
            }

            if (! is_null($mutate)) {
                $mutate($return);
            }

            $return->{OrderReturnSchema::STATUS} = $status;
            $return->save();

            return $return->refresh()->load(OrderReturnSchema::RES_ORDER);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(OrderReturn $return): array
    {
        return [
            OrderReturnSchema::ID => (int) $return->{OrderReturnSchema::ID},
            OrderReturnSchema::ORDER_ID => (int) $return->{OrderReturnSchema::ORDER_ID},
            OrderSchema::ORDER_CODE => (string) $return->{OrderReturnSchema::RES_ORDER}?->{OrderSchema::ORDER_CODE},
            OrderSchema::USER_ID => (int) $return->{OrderReturnSchema::USER_ID},
            OrderReturnSchema::STATUS => $return->{OrderReturnSchema::STATUS}->value,
            'locale' => Locale::fromRequest(),
        ];
    }
}
