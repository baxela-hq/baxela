<?php

namespace Modules\Core\Contracts\Gateways\Discount;

interface DiscountGatewayInterface
{
    /**
     * Advisory eligibility check plus the computed discount in minor units.
     * Answers "can this coupon currently be used?" — it is NOT the authority
     * for usage limits; only recordRedemption() is.
     *
     * $subtotalMinor is the merchandise subtotal (Σ price_snapshot ×
     * quantity), before any discount and excluding shipping/tax.
     */
    public function evaluate(string $code, int $subtotalMinor, int $userId): CouponEvaluation;

    /**
     * Authoritatively consume one unit of coupon capacity.
     *
     * PRECONDITION: the caller must hold an open DB transaction — this
     * method never opens or commits its own, so the order, the redemption
     * row and usage_count commit or roll back together. Throws when called
     * outside a transaction.
     *
     * Locks the coupon row FOR UPDATE, re-verifies the global and per-user
     * limits, inserts the redemption and increments usage_count.
     */
    public function recordRedemption(int $couponId, int $orderId, int $userId, int $discountMinor): RedemptionResult;

    /**
     * Release the redemption recorded for an order (unpaid cancellation or
     * expiry), restoring coupon capacity.
     *
     * Same transaction contract as recordRedemption(). Idempotent: returns
     * false without touching usage_count when no redemption exists; the
     * counter is decremented only when a row was actually deleted, under
     * the coupon row lock.
     */
    public function releaseRedemption(int $orderId): bool;
}
