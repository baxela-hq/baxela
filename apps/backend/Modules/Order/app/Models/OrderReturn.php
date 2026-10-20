<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Database\Factories\OrderReturnFactory;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnStatusEnum;

/**
 * @mixin Builder
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, OrderReturnSchema::ORDER_ID);
    }
}
