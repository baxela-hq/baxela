<?php

return [
    'fa' => [
        'auth' => [
            'user' => [
                'user' => [
                    'signed_in' => [
                        'subject' => 'ورود جدید به حساب شما',
                        'body' => 'یک ورود جدید به حساب شما شناسایی شده است',
                    ],
                ],
            ],
        ],
        'order' => [
            'admin' => [
                'order' => [
                    'created' => [
                        'subject' => 'سفارش جدید :order_code',
                        'body' => 'یک سفارش جدید با کد :order_code به مبلغ :amount ثبت شد.',
                    ],
                ],
            ],
            'user' => [
                'order' => [
                    'created' => [
                        'subject' => 'سفارش :order_code ثبت شد',
                        'body' => 'از خرید شما سپاسگزاریم. سفارش :order_code به مبلغ :amount در انتظار پرداخت است.',
                    ],
                    'paid' => [
                        'subject' => 'پرداخت سفارش :order_code دریافت شد',
                        'body' => 'پرداخت شما به مبلغ :amount برای سفارش :order_code دریافت شد.',
                    ],
                    'shipped' => [
                        'subject' => 'سفارش :order_code ارسال شد',
                        'body' => 'سفارش شما با کد :order_code ارسال شد.',
                    ],
                    'completed' => [
                        'subject' => 'سفارش :order_code تکمیل شد',
                        'body' => 'سفارش :order_code تحویل داده شد. از خرید شما متشکریم!',
                    ],
                    'cancelled' => [
                        'subject' => 'سفارش :order_code لغو شد',
                        'body' => 'سفارش :order_code لغو شد.',
                    ],
                ],
            ],
        ],
        'payment' => [
            'admin' => [
                'payment' => [
                    'succeeded' => [
                        'subject' => 'پرداخت دریافت شد',
                        'body' => 'یک پرداخت به مبلغ :amount برای سفارش :order_code انجام شد.',
                    ],
                    'failed' => [
                        'subject' => 'پرداخت ناموفق',
                        'body' => 'یک پرداخت به مبلغ :amount برای سفارش :order_code ناموفق بود.',
                    ],
                ],
            ],
            'user' => [
                'payment' => [
                    'failed' => [
                        'subject' => 'پرداخت سفارش :order_code ناموفق بود',
                        'body' => 'پرداخت شما به مبلغ :amount برای سفارش :order_code انجام نشد. لطفاً دوباره تلاش کنید.',
                    ],
                ],
            ],
        ],
    ],
];
