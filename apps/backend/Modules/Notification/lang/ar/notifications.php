<?php

return [
    'ar' => [
        'content' => [
            'admin' => [
                'post_comment' => [
                    'created' => [
                        'subject' => 'تعليق جديد في انتظار الموافقة',
                        'body' => 'تعليق جديد على المقال :post_id في انتظار المراجعة: «:excerpt»',
                    ],
                ],
            ],
            'user' => [
                'post_comment' => [
                    'approved' => [
                        'subject' => 'تمت الموافقة على تعليقك',
                        'body' => 'خبر سار — تمت الموافقة على تعليقك «:excerpt» على المقال :post_id وأصبح مرئيًا الآن.',
                    ],
                ],
            ],
        ],
        'order' => [
            'admin' => [
                'return' => [
                    'requested' => [
                        'subject' => 'طلب إرجاع جديد للطلب :order_code',
                        'body' => 'تم طلب إرجاع للطلب :order_code (السبب: :reason).',
                    ],
                ],
                'order' => [
                    'created' => [
                        'subject' => 'طلب جديد :order_code',
                        'body' => 'تم تقديم طلب جديد :order_code بقيمة :amount.',
                    ],
                ],
            ],
            'user' => [
                'order' => [
                    'refunded' => [
                        'subject' => 'تم استرداد مبلغ الطلب :order_code',
                        'body' => 'تم استرداد كامل مبلغ طلبك :order_code (:amount).',
                    ],
                    'created' => [
                        'subject' => 'تأكيد الطلب :order_code',
                        'body' => 'شكرًا لشرائك منّا. إجمالي طلبك :order_code هو :amount وهو في انتظار السداد.',
                    ],
                    'paid' => [
                        'subject' => 'تم استلام الدفع للطلب :order_code',
                        'body' => 'استلمنا دفعتك بمبلغ :amount للطلب :order_code.',
                    ],
                    'shipped' => [
                        'subject' => 'تم شحن الطلب :order_code',
                        'body' => 'خبر سار — الطلب :order_code في الطريق إليك.',
                    ],
                    'completed' => [
                        'subject' => 'اكتمل الطلب :order_code',
                        'body' => 'تم تسليم الطلب :order_code. نتمنى لك الاستمتاع بمشترياتك!',
                    ],
                    'cancelled' => [
                        'subject' => 'تم إلغاء الطلب :order_code',
                        'body' => 'تم إلغاء الطلب :order_code.',
                    ],
                ],
                'return' => [
                    'approved' => [
                        'subject' => 'تمت الموافقة على الإرجاع للطلب :order_code',
                        'body' => 'تمت الموافقة على طلب الإرجاع للطلب :order_code — يمكنك الآن إرسال المنتجات مرة أخرى.',
                    ],
                    'rejected' => [
                        'subject' => 'تم رفض الإرجاع للطلب :order_code',
                        'body' => 'تم رفض طلب الإرجاع للطلب :order_code.',
                    ],
                    'received' => [
                        'subject' => 'تم استلام الإرجاع للطلب :order_code',
                        'body' => 'استلمنا مرتجعك للطلب :order_code.',
                    ],
                    'completed' => [
                        'subject' => 'اكتمل الإرجاع للطلب :order_code',
                        'body' => 'اكتمل إرجاعك للطلب :order_code.',
                    ],
                ],
            ],
        ],
        'payment' => [
            'admin' => [
                'payment' => [
                    'succeeded' => [
                        'subject' => 'تم استلام الدفعة',
                        'body' => 'تم استلام دفعة بمبلغ :amount للطلب :order_code.',
                    ],
                    'failed' => [
                        'subject' => 'فشل الدفع',
                        'body' => 'فشلت دفعة بمبلغ :amount للطلب :order_code.',
                    ],
                ],
            ],
            'user' => [
                'payment' => [
                    'failed' => [
                        'subject' => 'فشل الدفع للطلب :order_code',
                        'body' => 'تعذّر معالجة دفعتك بمبلغ :amount للطلب :order_code. يُرجى المحاولة مرة أخرى.',
                    ],
                ],
            ],
        ],
        'support' => [
            'admin' => [
                'ticket' => [
                    'created' => [
                        'subject' => 'تذكرة دعم جديدة: :subject',
                        'body' => 'تم فتح تذكرة دعم جديدة «:subject».',
                    ],
                    'replied' => [
                        'subject' => 'رد العميل على التذكرة: :subject',
                        'body' => 'رد أحد العملاء على تذكرة الدعم «:subject».',
                    ],
                ],
            ],
            'user' => [
                'ticket' => [
                    'answered' => [
                        'subject' => 'رد على تذكرتك: :subject',
                        'body' => 'رد فريق الدعم على تذكرتك «:subject».',
                    ],
                ],
            ],
        ],
    ],
];
