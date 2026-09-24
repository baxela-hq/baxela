<?php

namespace Modules\Discount\Gateways;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Gateways\Discount\CouponEvaluation;
use Modules\Core\Contracts\Gateways\Discount\CouponFailureEnum;
use Modules\Core\Contracts\Gateways\Discount\DiscountGatewayInterface;
use Modules\Core\Contracts\Gateways\Discount\DTOs\CouponDiscountResult;
use Modules\Core\Contracts\Gateways\Discount\RedemptionResult;
use Modules\Core\Support\Money;
use Modules\Discount\Exceptions\DiscountException;
use Modules\Discount\Exceptions\ErrorCodeEnum;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Coupon\CouponTypeEnum;
use Modules\Discount\Schemas\Redemption\RedemptionSchema;
use RuntimeException;

class DiscountGateway implements DiscountGatewayInterface
{
    /**
     * Advisory check: "can this coupon currently be used?" Usage limits are
     * re-verified authoritatively by recordRedemption() — a concurrent
     * request may consume capacity after this call returns.
     */
    public function evaluate(string $code, int $subtotalMinor, int $userId): CouponEvaluation
    {
        $coupon = Coupon::query()
            ->where(CouponSchema::CODE, strtoupper(trim($code)))
            ->first();

        if (is_null($coupon)) {
            return CouponEvaluation::failed(CouponFailureEnum::NOT_FOUND);
        }

        if (! $coupon->{CouponSchema::IS_ACTIVE}) {
            return CouponEvaluation::failed(CouponFailureEnum::INACTIVE);
        }

        $now = now();
        if (! is_null($coupon->{CouponSchema::STARTS_AT}) && $now->lt($coupon->{CouponSchema::STARTS_AT})) {
            return CouponEvaluation::failed(CouponFailureEnum::NOT_STARTED);
        }

        if (! is_null($coupon->{CouponSchema::ENDS_AT}) && $now->gt($coupon->{CouponSchema::ENDS_AT})) {
            return CouponEvaluation::failed(CouponFailureEnum::EXPIRED);
        }

        if (! is_null($coupon->{CouponSchema::USAGE_LIMIT})
            && $coupon->{CouponSchema::USAGE_COUNT} >= $coupon->{CouponSchema::USAGE_LIMIT}) {
            return CouponEvaluation::failed(CouponFailureEnum::USAGE_LIMIT);
        }

        if (! is_null($coupon->{CouponSchema::PER_USER_LIMIT})
            && $this->userRedemptionCount((int) $coupon->{CouponSchema::ID}, $userId) >= $coupon->{CouponSchema::PER_USER_LIMIT}) {
            return CouponEvaluation::failed(CouponFailureEnum::PER_USER_LIMIT);
        }

        if (! is_null($coupon->{CouponSchema::MIN_ORDER_AMOUNT})
            && $subtotalMinor < Money::fromDecimal((string) $coupon->{CouponSchema::MIN_ORDER_AMOUNT})) {
            return CouponEvaluation::failed(CouponFailureEnum::MIN_ORDER_AMOUNT);
        }

        return CouponEvaluation::eligible(CouponDiscountResult::fill([
            'coupon_id' => (int) $coupon->{CouponSchema::ID},
            'code' => $coupon->{CouponSchema::CODE},
            'type' => $coupon->{CouponSchema::TYPE}->value,
            'discount_minor' => $this->computeDiscountMinor($coupon, $subtotalMinor),
        ]));
    }

    /**
     * Authoritative consumption. The coupon row lock serializes every
     * redemption/counter mutation of this coupon, so the limit checks below
     * are final — not optimistic re-reads of what evaluate() saw.
     */
    public function recordRedemption(int $couponId, int $orderId, int $userId, int $discountMinor): RedemptionResult
    {
        $this->requireOpenTransaction();

        /** @var Coupon $coupon */
        $coupon = Coupon::query()
            ->where(CouponSchema::ID, $couponId)
            ->lockForUpdate()
            ->first();

        if (is_null($coupon)) {
            // The coupon vanished between evaluation and redemption (e.g.
            // deleted mid-checkout) — exceptional, not a limit outcome
            throw new DiscountException(ErrorCodeEnum::COUPON_NOT_FOUND->value);
        }

        if (! is_null($coupon->{CouponSchema::USAGE_LIMIT})
            && $coupon->{CouponSchema::USAGE_COUNT} >= $coupon->{CouponSchema::USAGE_LIMIT}) {
            return RedemptionResult::GLOBAL_LIMIT_REACHED;
        }

        if (! is_null($coupon->{CouponSchema::PER_USER_LIMIT})
            && $this->userRedemptionCount($couponId, $userId) >= $coupon->{CouponSchema::PER_USER_LIMIT}) {
            return RedemptionResult::PER_USER_LIMIT_REACHED;
        }

        Redemption::query()->create([
            RedemptionSchema::COUPON_ID => $couponId,
            RedemptionSchema::ORDER_ID => $orderId,
            RedemptionSchema::USER_ID => $userId,
            RedemptionSchema::DISCOUNT_AMOUNT => Money::toDecimal($discountMinor),
        ]);

        $coupon->{CouponSchema::USAGE_COUNT} = (int) $coupon->{CouponSchema::USAGE_COUNT} + 1;
        $coupon->save();

        return RedemptionResult::REDEEMED;
    }

    /**
     * Idempotent release. Normative sequence — implement literally and do
     * not optimize the post-lock re-read away: the redemption may have been
     * released by a concurrent request in the window between the first
     * (unlocked) read and the coupon lock, and an unlocked decrement would
     * corrupt the usage_count == retained-redemptions invariant.
     */
    public function releaseRedemption(int $orderId): bool
    {
        $this->requireOpenTransaction();

        $redemption = Redemption::query()
            ->where(RedemptionSchema::ORDER_ID, $orderId)
            ->first();

        if (is_null($redemption)) {
            return false;
        }

        /** @var Coupon|null $coupon */
        $coupon = Coupon::query()
            ->where(CouponSchema::ID, $redemption->{RedemptionSchema::COUPON_ID})
            ->lockForUpdate()
            ->first();

        // REQUIRED re-read under the lock — see the docblock above
        $redemption = Redemption::query()
            ->where(RedemptionSchema::ORDER_ID, $orderId)
            ->lockForUpdate()
            ->first();

        if (is_null($redemption)) {
            return false;
        }

        $redemption->delete();

        if (! is_null($coupon)) {
            $coupon->{CouponSchema::USAGE_COUNT} = max(0, (int) $coupon->{CouponSchema::USAGE_COUNT} - 1);
            $coupon->save();
        }

        return true;
    }

    private function userRedemptionCount(int $couponId, int $userId): int
    {
        return (int) Redemption::query()
            ->where(RedemptionSchema::COUPON_ID, $couponId)
            ->where(RedemptionSchema::USER_ID, $userId)
            ->count();
    }

    /**
     * Percent: subtotal × value/100 rounded half-up once, capped by the
     * optional max_discount_amount and by the subtotal itself. Fixed: the
     * face value clamped to the subtotal. A result rounding to 0 is valid —
     * eligibility and the resulting amount are separate concepts.
     */
    private function computeDiscountMinor(Coupon $coupon, int $subtotalMinor): int
    {
        $discountMinor = $coupon->{CouponSchema::TYPE} === CouponTypeEnum::PERCENT
            ? Money::applyPercent($subtotalMinor, (string) $coupon->{CouponSchema::VALUE})
            : Money::fromDecimal((string) $coupon->{CouponSchema::VALUE});

        if ($coupon->{CouponSchema::TYPE} === CouponTypeEnum::PERCENT
            && ! is_null($coupon->{CouponSchema::MAX_DISCOUNT_AMOUNT})) {
            $discountMinor = Money::min($discountMinor, Money::fromDecimal((string) $coupon->{CouponSchema::MAX_DISCOUNT_AMOUNT}));
        }

        return Money::min($discountMinor, $subtotalMinor);
    }

    /**
     * Both mutators must participate in the caller's transaction so the
     * order, redemption row and counter commit or roll back together; an
     * independent commit here would break that atomicity.
     */
    private function requireOpenTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('DiscountGateway redemption methods require an open database transaction.');
        }
    }
}
