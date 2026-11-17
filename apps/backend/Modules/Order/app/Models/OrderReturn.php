<?php

namespace Modules\Order\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Database\Factories\OrderReturnFactory;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnStatusEnum;

/**
 * @mixin Builder<OrderReturn>
 */
/**
 * @property int $id
 * @property int $order_id
 * @property int $user_id
 * @property OrderReturnStatusEnum $status
 * @property string $reason
 * @property string|null $note
 * @property string|null $admin_note
 * @property string|null $tracking_number
 * @property CarbonInterface|null $stock_restored_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Order $order
 */
class OrderReturn extends Model
{
    use HasFactory;

    protected $table = OrderReturnSchema::TABLE;

    protected $fillable = [
        OrderReturnSchema::ORDER_ID,
        OrderReturnSchema::USER_ID,
        OrderReturnSchema::STATUS,
        OrderReturnSchema::REASON,
        OrderReturnSchema::NOTE,
        OrderReturnSchema::ADMIN_NOTE,
        OrderReturnSchema::TRACKING_NUMBER,
        OrderReturnSchema::STOCK_RESTORED_AT,
    ];

    protected function casts(): array
    {
        return [
            OrderReturnSchema::STATUS => OrderReturnStatusEnum::class,
            OrderReturnSchema::STOCK_RESTORED_AT => 'datetime',
        ];
    }

    protected static function newFactory(): OrderReturnFactory
    {
        return OrderReturnFactory::new();
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, OrderReturnSchema::ORDER_ID);
    }
}
