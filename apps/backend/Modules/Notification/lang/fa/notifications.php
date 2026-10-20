<?php

return [
    'fa' => [
        'content' => [
            'admin' => [
                'post_comment' => [
                    'created' => [
                        'subject' => 'دیدگاه جدید در انتظار تایید',
                        'body' => 'یک دیدگاه جدید روی نوشته :post_id در انتظار بررسی است: «:excerpt»',
                    ],
                ],
            ],
            'user' => [
                'post_comment' => [
                    'approved' => [
                        'subject' => 'دیدگاه شما تایید شد',
                        'body' => 'خبر خوب — دیدگاه شما «:excerpt» روی نوشته :post_id تایید شد و اکنون نمایش داده می‌شود.',
                    ],
                ],
            ],
        ],
        'order' => [
            'admin' => [
                'return' => [
                    'requested' => [
                        'subject' => 'درخواست مرجوعی جدید برای :order_code',
                        'body' => 'برای سفارش :order_code درخواست مرجوعی ثبت شد (دلیل: :reason).',
                    ],
                ],
                'order' => [
                    'created' => [
                        'subject' => 'سفارش جدید :order_code',
                        'body' => 'یک سفارش جدید با کد :order_code به مبلغ :amount ثبت شد.',
                    ],
                ],
            ],
            'user' => [
                'order' => [
                    'refunded' => [
                        'subject' => 'سفارش :order_code بازپرداخت شد',
                        'body' => 'سفارش شما :order_code به‌طور کامل بازپرداخت شد (:amount).',
                    ],
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
                'return' => [
                    'approved' => [
                        'subject' => 'مرجوعی :order_code تایید شد',
                        'body' => 'درخواست مرجوعی سفارش :order_code تایید شد — می‌توانید کالا را بازگردانید.',
                    ],
                    'rejected' => [
                        'subject' => 'مرجوعی :order_code رد شد',
                        'body' => 'درخواست مرجوعی سفارش :order_code رد شد.',
                    ],
                    'received' => [
                        'subject' => 'مرجوعی :order_code دریافت شد',
                        'body' => 'مرجوعی سفارش :order_code دریافت شد.',
                    ],
                    'completed' => [
                        'subject' => 'مرجوعی :order_code تکمیل شد',
                        'body' => 'فرآیند مرجوعی سفارش :order_code تکمیل شد.',
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
        'support' => [
            'admin' => [
                'ticket' => [
                    'created' => [
                        'subject' => 'تیکت پشتیبانی جدید: :subject',
                        'body' => 'یک تیکت پشتیبانی جدید با عنوان «:subject» ثبت شد.',
                    ],
                    'replied' => [
                        'subject' => 'پاسخ مشتری به تیکت: :subject',
                        'body' => 'مشتری به تیکت پشتیبانی «:subject» پاسخ داد.',
                    ],
                ],
            ],
            'user' => [
                'ticket' => [
                    'answered' => [
                        'subject' => 'پاسخ به تیکت شما: :subject',
                        'body' => 'تیم پشتیبانی به تیکت «:subject» شما پاسخ داد.',
                    ],
                ],
            ],
        ],
    ],
];
