<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Database\Factories\OrderItemFactory;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;

// use Modules\Order\Database\Factories\OrderItemFactory;

class OrderItem extends Model
{
    use HasFactory;

    protected $table = OrderItemSchema::TABLE;

    public $timestamps = false;

    protected $fillable = [
        OrderItemSchema::ORDER_ID,
        OrderItemSchema::VARIANT_ID,
        OrderItemSchema::PRODUCT_NAME_SNAPSHOT,
        OrderItemSchema::PRODUCT_SLUG_SNAPSHOT,
        OrderItemSchema::PRICE_SNAPSHOT,
        OrderItemSchema::QUANTITY,
    ];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }
}
