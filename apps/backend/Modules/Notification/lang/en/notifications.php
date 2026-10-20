<?php

return [
    'en' => [
        'content' => [
            'admin' => [
                'post_comment' => [
                    'created' => [
                        'subject' => 'New comment pending approval',
                        'body' => 'A new comment on post :post_id awaits moderation: ":excerpt"',
                    ],
                ],
            ],
            'user' => [
                'post_comment' => [
                    'approved' => [
                        'subject' => 'Your comment was approved',
                        'body' => 'Good news — your comment ":excerpt" on post :post_id has been approved and is now visible.',
                    ],
                ],
            ],
        ],
        'order' => [
            'admin' => [
                'return' => [
                    'requested' => [
                        'subject' => 'New return request for :order_code',
                        'body' => 'A return was requested for order :order_code (reason: :reason).',
                    ],
                ],
                'order' => [
                    'created' => [
                        'subject' => 'New order :order_code',
                        'body' => 'A new order :order_code for :amount has been placed.',
                    ],
                ],
            ],
            'user' => [
                'order' => [
                    'refunded' => [
                        'subject' => 'Order :order_code refunded',
                        'body' => 'Your order :order_code has been fully refunded (:amount).',
                    ],
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
                'return' => [
                    'approved' => [
                        'subject' => 'Return approved for :order_code',
                        'body' => 'Your return request for order :order_code was approved — you can ship the items back.',
                    ],
                    'rejected' => [
                        'subject' => 'Return rejected for :order_code',
                        'body' => 'Your return request for order :order_code was rejected.',
                    ],
                    'received' => [
                        'subject' => 'Return received for :order_code',
                        'body' => 'We received your return for order :order_code.',
                    ],
                    'completed' => [
                        'subject' => 'Return completed for :order_code',
                        'body' => 'Your return for :order_code is complete.',
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
        'support' => [
            'admin' => [
                'ticket' => [
                    'created' => [
                        'subject' => 'New support ticket: :subject',
                        'body' => 'A new support ticket ":subject" was opened.',
                    ],
                    'replied' => [
                        'subject' => 'Customer replied to ticket: :subject',
                        'body' => 'A customer replied to the support ticket ":subject".',
                    ],
                ],
            ],
            'user' => [
                'ticket' => [
                    'answered' => [
                        'subject' => 'Reply to your ticket: :subject',
                        'body' => 'Our support team replied to your ticket ":subject".',
                    ],
                ],
            ],
        ],
    ],
];
