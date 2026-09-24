<?php

namespace Modules\Core\Exceptions\Discount;

use Modules\Core\Contracts\Gateways\Discount\RedemptionResult;
use Modules\Core\Exceptions\BaseException;
use Throwable;

/**
 * Thrown by order creation when the authoritative redemption is refused.
 * Internal signal only — callers (checkout) translate the reason into
 * their own audience-facing error codes and let the transaction roll back.
 */
class RedemptionRefusedException extends BaseException
{
    public function __construct(
        public readonly RedemptionResult $reason,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct('discount.redemption.refused', $httpStatus, $meta, $isSafe, $previous);
    }
}
