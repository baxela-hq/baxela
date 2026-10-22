<?php

return [
    'es' => [
        'content' => [
            'admin' => [
                'post_comment' => [
                    'created' => [
                        'subject' => 'Nuevo comentario pendiente de aprobación',
                        'body' => 'Un nuevo comentario en el artículo :post_id espera moderación: «:excerpt»',
                    ],
                ],
            ],
            'user' => [
                'post_comment' => [
                    'approved' => [
                        'subject' => 'Tu comentario fue aprobado',
                        'body' => 'Buenas noticias: tu comentario «:excerpt» en el artículo :post_id fue aprobado y ya es visible.',
                    ],
                ],
            ],
        ],
        'order' => [
            'admin' => [
                'return' => [
                    'requested' => [
                        'subject' => 'Nueva solicitud de devolución para :order_code',
                        'body' => 'Se solicitó una devolución para el pedido :order_code (motivo: :reason).',
                    ],
                ],
                'order' => [
                    'created' => [
                        'subject' => 'Nuevo pedido :order_code',
                        'body' => 'Se realizó un nuevo pedido :order_code por :amount.',
                    ],
                ],
            ],
            'user' => [
                'order' => [
                    'refunded' => [
                        'subject' => 'Pedido :order_code reembolsado',
                        'body' => 'Tu pedido :order_code fue reembolsado en su totalidad (:amount).',
                    ],
                    'created' => [
                        'subject' => 'Pedido :order_code confirmado',
                        'body' => 'Gracias por tu compra. Tu pedido :order_code suma un total de :amount y está pendiente de pago.',
                    ],
                    'paid' => [
                        'subject' => 'Pago recibido del pedido :order_code',
                        'body' => 'Recibimos tu pago de :amount por el pedido :order_code.',
                    ],
                    'shipped' => [
                        'subject' => 'Pedido :order_code enviado',
                        'body' => 'Buenas noticias: el pedido :order_code está en camino.',
                    ],
                    'completed' => [
                        'subject' => 'Pedido :order_code completado',
                        'body' => 'El pedido :order_code fue entregado. ¡Disfruta tu compra!',
                    ],
                    'cancelled' => [
                        'subject' => 'Pedido :order_code cancelado',
                        'body' => 'El pedido :order_code fue cancelado.',
                    ],
                ],
                'return' => [
                    'approved' => [
                        'subject' => 'Devolución aprobada para :order_code',
                        'body' => 'Tu solicitud de devolución del pedido :order_code fue aprobada — ya puedes enviar los artículos de vuelta.',
                    ],
                    'rejected' => [
                        'subject' => 'Devolución rechazada para :order_code',
                        'body' => 'Tu solicitud de devolución del pedido :order_code fue rechazada.',
                    ],
                    'received' => [
                        'subject' => 'Devolución recibida de :order_code',
                        'body' => 'Recibimos tu devolución del pedido :order_code.',
                    ],
                    'completed' => [
                        'subject' => 'Devolución completada para :order_code',
                        'body' => 'Tu devolución del pedido :order_code está completa.',
                    ],
                ],
            ],
        ],
        'payment' => [
            'admin' => [
                'payment' => [
                    'succeeded' => [
                        'subject' => 'Pago recibido',
                        'body' => 'Se recibió un pago de :amount por el pedido :order_code.',
                    ],
                    'failed' => [
                        'subject' => 'Pago fallido',
                        'body' => 'Un pago de :amount por el pedido :order_code falló.',
                    ],
                ],
            ],
            'user' => [
                'payment' => [
                    'failed' => [
                        'subject' => 'Pago fallido del pedido :order_code',
                        'body' => 'Tu pago de :amount por el pedido :order_code no pudo procesarse. Inténtalo de nuevo.',
                    ],
                ],
            ],
        ],
        'support' => [
            'admin' => [
                'ticket' => [
                    'created' => [
                        'subject' => 'Nuevo ticket de soporte: :subject',
                        'body' => 'Se abrió un nuevo ticket de soporte: «:subject».',
                    ],
                    'replied' => [
                        'subject' => 'Cliente respondió al ticket: :subject',
                        'body' => 'Un cliente respondió al ticket de soporte «:subject».',
                    ],
                ],
            ],
            'user' => [
                'ticket' => [
                    'answered' => [
                        'subject' => 'Respuesta a tu ticket: :subject',
                        'body' => 'Nuestro equipo de soporte respondió a tu ticket «:subject».',
                    ],
                ],
            ],
        ],
    ],
];
