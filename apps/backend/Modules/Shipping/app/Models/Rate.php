<?php

namespace Modules\Shipping\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Shipping\Database\Factories\RateFactory;
use Modules\Shipping\Schemas\Rate\RateSchema;

/**
 * @property int $id
 * @property int $method_id
 * @property int $zone_id
 * @property string $price
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Method $method
 * @property-read Zone $zone
 */
class Rate extends Model
{
    /** @use HasFactory<RateFactory> */
    use HasFactory;

    use TenantScopedTrait;

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

    /** @return BelongsTo<Method, $this> */
    public function method(): BelongsTo
    {
        return $this->belongsTo(Method::class);
    }

    /** @return BelongsTo<Zone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
