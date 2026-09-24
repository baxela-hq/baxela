<?php

return [
    'checkout.empty' => 'The cart is empty',
    'checkout.invalid_address' => 'The address was not found',
    'checkout.invalid_shipping_method' => 'The shipping method is not available for this address',
    'checkout.order_failed' => 'Placing the order failed. Please try again.',
    'checkout.out_of_stock' => ':name in your cart is out of stock',
    'checkout.insufficient_stock' => 'Only :available left in stock for :name in your cart',
    'item.out_of_stock' => ':name is out of stock',
    'item.insufficient_stock' => 'Only :available left in stock for :name — reduce the quantity',
    'token.missing' => 'The X-Cart-Token header is required',
    'token.invalid' => 'The X-Cart-Token header is not a valid UUID',
    'coupon.not_found' => 'The coupon code is not valid',
    'coupon.inactive' => 'This coupon is no longer active',
    'coupon.not_started' => 'This coupon is not active yet',
    'coupon.expired' => 'This coupon has expired',
    'coupon.usage_limit' => 'This coupon has reached its usage limit',
    'coupon.per_user_limit' => 'You have already used this coupon the maximum number of times',
    'coupon.min_order_amount' => 'The order does not meet the minimum amount for this coupon',
];
