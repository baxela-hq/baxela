<?php

return [
    'fr' => [
        'content' => [
            'admin' => [
                'post_comment' => [
                    'created' => [
                        'subject' => 'Nouveau commentaire en attente de modération',
                        'body' => 'Un nouveau commentaire sur l’article :post_id attend la modération : « :excerpt »',
                    ],
                ],
            ],
            'user' => [
                'post_comment' => [
                    'approved' => [
                        'subject' => 'Votre commentaire a été approuvé',
                        'body' => 'Bonne nouvelle — votre commentaire « :excerpt » sur l’article :post_id a été approuvé et est maintenant visible.',
                    ],
                ],
            ],
        ],
        'order' => [
            'admin' => [
                'return' => [
                    'requested' => [
                        'subject' => 'Nouvelle demande de retour pour :order_code',
                        'body' => 'Un retour a été demandé pour la commande :order_code (motif : :reason).',
                    ],
                ],
                'order' => [
                    'created' => [
                        'subject' => 'Nouvelle commande :order_code',
                        'body' => 'Une nouvelle commande :order_code d’un montant de :amount a été passée.',
                    ],
                ],
            ],
            'user' => [
                'order' => [
                    'refunded' => [
                        'subject' => 'Commande :order_code remboursée',
                        'body' => 'Votre commande :order_code a été intégralement remboursée (:amount).',
                    ],
                    'created' => [
                        'subject' => 'Commande :order_code confirmée',
                        'body' => 'Merci pour votre achat. Votre commande :order_code s’élève à :amount et est en attente de paiement.',
                    ],
                    'paid' => [
                        'subject' => 'Paiement reçu pour la commande :order_code',
                        'body' => 'Nous avons reçu votre paiement de :amount pour la commande :order_code.',
                    ],
                    'shipped' => [
                        'subject' => 'Commande :order_code expédiée',
                        'body' => 'Bonne nouvelle — la commande :order_code est en route.',
                    ],
                    'completed' => [
                        'subject' => 'Commande :order_code terminée',
                        'body' => 'La commande :order_code a été livrée. Profitez bien de votre achat !',
                    ],
                    'cancelled' => [
                        'subject' => 'Commande :order_code annulée',
                        'body' => 'La commande :order_code a été annulée.',
                    ],
                ],
                'return' => [
                    'approved' => [
                        'subject' => 'Retour approuvé pour :order_code',
                        'body' => 'Votre demande de retour pour la commande :order_code a été approuvée — vous pouvez renvoyer les articles.',
                    ],
                    'rejected' => [
                        'subject' => 'Retour refusé pour :order_code',
                        'body' => 'Votre demande de retour pour la commande :order_code a été refusée.',
                    ],
                    'received' => [
                        'subject' => 'Retour reçu pour :order_code',
                        'body' => 'Nous avons reçu votre retour pour la commande :order_code.',
                    ],
                    'completed' => [
                        'subject' => 'Retour terminé pour :order_code',
                        'body' => 'Votre retour pour :order_code est terminé.',
                    ],
                ],
            ],
        ],
        'payment' => [
            'admin' => [
                'payment' => [
                    'succeeded' => [
                        'subject' => 'Paiement reçu',
                        'body' => 'Un paiement de :amount a été reçu pour la commande :order_code.',
                    ],
                    'failed' => [
                        'subject' => 'Paiement échoué',
                        'body' => 'Un paiement de :amount pour la commande :order_code a échoué.',
                    ],
                ],
            ],
            'user' => [
                'payment' => [
                    'failed' => [
                        'subject' => 'Paiement échoué pour la commande :order_code',
                        'body' => 'Votre paiement de :amount pour la commande :order_code n’a pas pu être traité. Veuillez réessayer.',
                    ],
                ],
            ],
        ],
        'support' => [
            'admin' => [
                'ticket' => [
                    'created' => [
                        'subject' => 'Nouveau ticket d’assistance : :subject',
                        'body' => 'Un nouveau ticket d’assistance « :subject » a été ouvert.',
                    ],
                    'replied' => [
                        'subject' => 'Réponse du client au ticket : :subject',
                        'body' => 'Un client a répondu au ticket d’assistance « :subject ».',
                    ],
                ],
            ],
            'user' => [
                'ticket' => [
                    'answered' => [
                        'subject' => 'Réponse à votre ticket : :subject',
                        'body' => 'Notre équipe d’assistance a répondu à votre ticket « :subject ».',
                    ],
                ],
            ],
        ],
    ],
];
