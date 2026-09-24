<?php

namespace Modules\Discount\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Discount\Database\Factories\RedemptionFactory;
use Modules\Discount\Schemas\Redemption\RedemptionSchema;

/**
 * SOLE-WRITER INVARIANT: in production code, rows in this table (and the
 * coupon usage_count they mirror) are created and deleted exclusively by
 * DiscountGateway::recordRedemption()/releaseRedemption() while holding the
 * coupon row lock. Never call Redemption::create() elsewhere — a direct
 * write would bypass the concurrency guarantees those methods provide.
 * (Tests may seed rows directly.)
 *
 * This table is the source of truth for CURRENT usage state, not a
 * permanent audit history: releasing a redemption physically deletes the
 * row (by design — released capacity becomes reusable).
 *
 * @mixin Builder
 */
class Redemption extends Model
{
    use HasFactory;

    protected $table = RedemptionSchema::TABLE;

    protected $fillable = [
        RedemptionSchema::COUPON_ID,
        RedemptionSchema::ORDER_ID,
        RedemptionSchema::USER_ID,
        RedemptionSchema::DISCOUNT_AMOUNT,
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    protected static function newFactory(): RedemptionFactory
    {
        return RedemptionFactory::new();
    }
}
