<?php

namespace Modules\Cart\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case CHECKOUT_EMPTY = 'cart.checkout.empty';
    case CHECKOUT_INVALID_ADDRESS = 'cart.checkout.invalid_address';
    case CHECKOUT_INVALID_SHIPPING_METHOD = 'cart.checkout.invalid_shipping_method';
    case CHECKOUT_ORDER_FAILED = 'cart.checkout.order_failed';
    case CHECKOUT_OUT_OF_STOCK = 'cart.checkout.out_of_stock';
    case CHECKOUT_INSUFFICIENT_STOCK = 'cart.checkout.insufficient_stock';
    case ITEM_OUT_OF_STOCK = 'cart.item.out_of_stock';
    case ITEM_INSUFFICIENT_STOCK = 'cart.item.insufficient_stock';
    case TOKEN_MISSING = 'cart.token.missing';
    case TOKEN_INVALID = 'cart.token.invalid';
    case COUPON_NOT_FOUND = 'cart.coupon.not_found';
    case COUPON_INACTIVE = 'cart.coupon.inactive';
    case COUPON_NOT_STARTED = 'cart.coupon.not_started';
    case COUPON_EXPIRED = 'cart.coupon.expired';
    case COUPON_USAGE_LIMIT = 'cart.coupon.usage_limit';
    case COUPON_PER_USER_LIMIT = 'cart.coupon.per_user_limit';
    case COUPON_MIN_ORDER_AMOUNT = 'cart.coupon.min_order_amount';
}
