<?php

return [
    'class' => [
        'creation_failed' => 'Échec de la création de la classe de taxe',
        'update_failed' => 'Échec de la mise à jour de la classe de taxe',
        'delete_default' => 'La classe de taxe par défaut ne peut pas être supprimée ; désignez d’abord une autre classe par défaut',
        'in_use' => 'La classe de taxe est encore attribuée à des produits et ne peut pas être supprimée',
    ],
    'rate' => [
        'creation_failed' => 'Échec de la création du taux de taxe',
        'update_failed' => 'Échec de la mise à jour du taux de taxe',
    ],
    'configuration' => [
        'invalid_class' => 'Un produit référence une classe de taxe qui n’existe plus ; le paiement ne peut pas aboutir',
        'no_default' => 'Aucune classe de taxe par défaut n’est configurée',
    ],
];
