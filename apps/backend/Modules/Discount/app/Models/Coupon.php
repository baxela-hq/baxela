<?php

namespace Modules\Discount\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Discount\Database\Factories\CouponFactory;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Coupon\CouponTypeEnum;

/**
 * @mixin Builder
 */
class Coupon extends Model
{
    use HasFactory;

    protected $table = CouponSchema::TABLE;

    protected $fillable = [
        CouponSchema::CODE,
        CouponSchema::NAME,
        CouponSchema::TYPE,
        CouponSchema::VALUE,
        CouponSchema::MAX_DISCOUNT_AMOUNT,
        CouponSchema::MIN_ORDER_AMOUNT,
        CouponSchema::STARTS_AT,
        CouponSchema::ENDS_AT,
        CouponSchema::USAGE_LIMIT,
        CouponSchema::PER_USER_LIMIT,
        CouponSchema::USAGE_COUNT,
        CouponSchema::IS_ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            CouponSchema::TYPE => CouponTypeEnum::class,
            CouponSchema::STARTS_AT => 'datetime',
            CouponSchema::ENDS_AT => 'datetime',
            CouponSchema::IS_ACTIVE => 'boolean',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }

    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }
}
