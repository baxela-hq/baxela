<?php

namespace Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shipping\Database\Factories\RateFactory;
use Modules\Shipping\Schemas\Rate\RateSchema;

class Rate extends Model
{
    use HasFactory;

    protected $table = RateSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        RateSchema::METHOD_ID,
        RateSchema::ZONE_ID,
        RateSchema::PRICE,
    ];

    protected static function newFactory(): RateFactory
    {
        return RateFactory::new();
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(Method::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
