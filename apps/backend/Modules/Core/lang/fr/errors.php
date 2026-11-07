<?php

return [
    '401' => 'Non autorisé',
    '403' => 'Accès interdit',
    '404' => 'Ressource introuvable',
    '422' => 'Échec de la validation',
    '429' => 'Trop de requêtes. Veuillez réessayer plus tard.',
    '500' => 'Une erreur est survenue',
    'internal_server_error' => 'Une erreur est survenue',

    // Failed-jobs admin surface (gap-B3) — agent-drafted, pending owner review.
    'failed_jobs' => [
        'not_found' => "Le travail en échec n'a pas été trouvé",
        'search_too_short' => "La recherche d'exception doit comporter au moins :min caractères",
    ],
];
