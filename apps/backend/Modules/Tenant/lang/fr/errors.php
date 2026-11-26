<?php

return [
    '401' => 'Unauthorized',
    '403' => 'Forbidden',
    '404' => 'Resource not found',
    '422' => 'Validation failed',
    '429' => 'Too many requests. Please try again later.',
    '500' => 'Something went wrong',

    'tenant' => [
        'creation_failed' => 'La boutique n\'a pas pu être créée',
        'update_failed' => 'La boutique n\'a pas pu être mise à jour',
        'slug_taken' => 'Cette adresse de boutique est déjà prise',
        'status_change_failed' => 'Le statut de la boutique n\'a pas pu être modifié',
        'deletion_failed' => 'La boutique n\'a pas pu être supprimée',
    ],

    'domain' => [
        'attachment_failed' => 'Le domaine n\'a pas pu être rattaché à la boutique',
        'release_failed' => 'Le domaine n\'a pas pu être libéré',
        'in_use' => 'Ce domaine appartient à une autre boutique',
        'already_attached' => 'Ce domaine est déjà rattaché à la boutique',
        'cooling_off' => 'Ce domaine a été libéré récemment et ne peut pas encore être rattaché à une autre boutique',
    ],

    'onboarding' => [
        'limit_reached' => 'Vous possédez déjà le nombre maximum de boutiques',
        'failed' => 'La boutique n\'a pas pu être créée',
    ],
];
