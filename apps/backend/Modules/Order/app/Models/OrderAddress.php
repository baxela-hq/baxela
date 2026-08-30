<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Database\Factories\OrderAddressFactory;
use Modules\Order\Schemas\OrderAddress\OrderAddressSchema;

class OrderAddress extends Model
{
    use HasFactory;

    protected $table = OrderAddressSchema::TABLE;

    public $timestamps = false;

    protected $fillable = [
        OrderAddressSchema::ORDER_ID,
        OrderAddressSchema::TYPE,
        OrderAddressSchema::FULL_NAME,
        OrderAddressSchema::PHONE,
        OrderAddressSchema::ADDRESS_LINE,
        OrderAddressSchema::CITY,
        OrderAddressSchema::POSTAL_CODE,
        OrderAddressSchema::COUNTRY_CODE,
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

    protected static function newFactory(): OrderAddressFactory
    {
        return OrderAddressFactory::new();
    }
}
