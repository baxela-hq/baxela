<?php

return [
    'en' => [
        'auth' => [
            'user' => [
                'user' => [
                    'signed_in' => [
                        'subject' => 'New Login to your account',
                        'body' => 'A new logged in has been identified to your account',
                    ],
                ],
            ],
        ],
        'order' => [
            'admin' => [
                'order' => [
                    'created' => [
                        'subject' => 'New order :order_code',
                        'body' => 'A new order :order_code for :amount has been placed.',
                    ],
                ],
            ],
            'user' => [
                'order' => [
                    'created' => [
                        'subject' => 'Order :order_code confirmed',
                        'body' => 'Thank you for your purchase. Your order :order_code totals :amount and is awaiting payment.',
                    ],
                    'paid' => [
                        'subject' => 'Payment received for order :order_code',
                        'body' => 'We received your payment of :amount for order :order_code.',
                    ],
                    'shipped' => [
                        'subject' => 'Order :order_code shipped',
                        'body' => 'Good news — order :order_code is on its way.',
                    ],
                    'completed' => [
                        'subject' => 'Order :order_code completed',
                        'body' => 'Order :order_code has been delivered. Enjoy your purchase!',
                    ],
                    'cancelled' => [
                        'subject' => 'Order :order_code cancelled',
                        'body' => 'Order :order_code has been cancelled.',
                    ],
                ],
            ],
        ],
        'payment' => [
            'admin' => [
                'payment' => [
                    'succeeded' => [
                        'subject' => 'Payment received',
                        'body' => 'A payment of :amount was received for order :order_code.',
                    ],
                    'failed' => [
                        'subject' => 'Payment failed',
                        'body' => 'A payment of :amount for order :order_code failed.',
                    ],
                ],
            ],
            'user' => [
                'payment' => [
                    'failed' => [
                        'subject' => 'Payment failed for order :order_code',
                        'body' => 'Your payment of :amount for order :order_code could not be processed. Please try again.',
                    ],
                ],
            ],
        ],
    ],
];
