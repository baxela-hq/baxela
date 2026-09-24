<?php

namespace Modules\Core\Contracts\Gateways\Discount;

/**
 * Authoritative outcome of a redemption attempt. A concurrent request may
 * consume capacity between the advisory evaluate() and this call, so
 * non-Redeemed results must abort the surrounding checkout.
 */
enum RedemptionResult: string
{
    case REDEEMED = 'redeemed';

    case GLOBAL_LIMIT_REACHED = 'global_limit_reached';

    case PER_USER_LIMIT_REACHED = 'per_user_limit_reached';
}
