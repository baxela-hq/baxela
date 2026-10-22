<?php

return [
    'de' => [
        'content' => [
            'admin' => [
                'post_comment' => [
                    'created' => [
                        'subject' => 'Neuer Kommentar wartet auf Freigabe',
                        'body' => 'Ein neuer Kommentar zum Beitrag :post_id wartet auf Moderation: „:excerpt“',
                    ],
                ],
            ],
            'user' => [
                'post_comment' => [
                    'approved' => [
                        'subject' => 'Ihr Kommentar wurde freigegeben',
                        'body' => 'Gute Nachricht — Ihr Kommentar „:excerpt“ zum Beitrag :post_id wurde freigegeben und ist jetzt sichtbar.',
                    ],
                ],
            ],
        ],
        'order' => [
            'admin' => [
                'return' => [
                    'requested' => [
                        'subject' => 'Neue Rücksendeanforderung für :order_code',
                        'body' => 'Für die Bestellung :order_code wurde eine Rücksendung angefordert (Grund: :reason).',
                    ],
                ],
                'order' => [
                    'created' => [
                        'subject' => 'Neue Bestellung :order_code',
                        'body' => 'Eine neue Bestellung :order_code über :amount wurde aufgegeben.',
                    ],
                ],
            ],
            'user' => [
                'order' => [
                    'refunded' => [
                        'subject' => 'Bestellung :order_code erstattet',
                        'body' => 'Ihre Bestellung :order_code wurde vollständig erstattet (:amount).',
                    ],
                    'created' => [
                        'subject' => 'Bestellung :order_code bestätigt',
                        'body' => 'Vielen Dank für Ihren Einkauf. Ihre Bestellung :order_code beläuft sich auf :amount und wartet auf die Zahlung.',
                    ],
                    'paid' => [
                        'subject' => 'Zahlung für Bestellung :order_code eingegangen',
                        'body' => 'Wir haben Ihre Zahlung über :amount für die Bestellung :order_code erhalten.',
                    ],
                    'shipped' => [
                        'subject' => 'Bestellung :order_code versandt',
                        'body' => 'Gute Nachricht — die Bestellung :order_code ist unterwegs.',
                    ],
                    'completed' => [
                        'subject' => 'Bestellung :order_code abgeschlossen',
                        'body' => 'Die Bestellung :order_code wurde zugestellt. Wir wünschen Ihnen viel Freude mit Ihrem Einkauf!',
                    ],
                    'cancelled' => [
                        'subject' => 'Bestellung :order_code storniert',
                        'body' => 'Die Bestellung :order_code wurde storniert.',
                    ],
                ],
                'return' => [
                    'approved' => [
                        'subject' => 'Rücksendung für :order_code genehmigt',
                        'body' => 'Ihre Rücksendeanforderung für die Bestellung :order_code wurde genehmigt — Sie können die Artikel jetzt zurücksenden.',
                    ],
                    'rejected' => [
                        'subject' => 'Rücksendung für :order_code abgelehnt',
                        'body' => 'Ihre Rücksendeanforderung für die Bestellung :order_code wurde abgelehnt.',
                    ],
                    'received' => [
                        'subject' => 'Rücksendung für :order_code eingegangen',
                        'body' => 'Wir haben Ihre Rücksendung für die Bestellung :order_code erhalten.',
                    ],
                    'completed' => [
                        'subject' => 'Rücksendung für :order_code abgeschlossen',
                        'body' => 'Ihre Rücksendung für :order_code ist abgeschlossen.',
                    ],
                ],
            ],
        ],
        'payment' => [
            'admin' => [
                'payment' => [
                    'succeeded' => [
                        'subject' => 'Zahlung eingegangen',
                        'body' => 'Eine Zahlung über :amount für die Bestellung :order_code ist eingegangen.',
                    ],
                    'failed' => [
                        'subject' => 'Zahlung fehlgeschlagen',
                        'body' => 'Eine Zahlung über :amount für die Bestellung :order_code ist fehlgeschlagen.',
                    ],
                ],
            ],
            'user' => [
                'payment' => [
                    'failed' => [
                        'subject' => 'Zahlung für Bestellung :order_code fehlgeschlagen',
                        'body' => 'Ihre Zahlung über :amount für die Bestellung :order_code konnte nicht verarbeitet werden. Bitte versuchen Sie es erneut.',
                    ],
                ],
            ],
        ],
        'support' => [
            'admin' => [
                'ticket' => [
                    'created' => [
                        'subject' => 'Neues Support-Ticket: :subject',
                        'body' => 'Ein neues Support-Ticket „:subject“ wurde eröffnet.',
                    ],
                    'replied' => [
                        'subject' => 'Kunde hat auf Ticket geantwortet: :subject',
                        'body' => 'Ein Kunde hat auf das Support-Ticket „:subject“ geantwortet.',
                    ],
                ],
            ],
            'user' => [
                'ticket' => [
                    'answered' => [
                        'subject' => 'Antwort auf Ihr Ticket: :subject',
                        'body' => 'Unser Support-Team hat auf Ihr Ticket „:subject“ geantwortet.',
                    ],
                ],
            ],
        ],
    ],
];
