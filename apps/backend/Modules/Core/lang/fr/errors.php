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

    // FX rate auto-sync (Part E) — sanitized, stable codes.
    'fx_no_default_currency' => 'Aucune devise par défaut n\'est configurée.',
    'base_mismatch' => 'Le fournisseur a renvoyé des taux pour une devise de base différente. Les taux n\'ont pas été modifiés.',
    'stale_rates' => 'Le fournisseur a renvoyé des taux périmés. Les taux n\'ont pas été modifiés.',
    'invalid_timestamp' => 'Le fournisseur a renvoyé un horodatage invalide. Les taux n\'ont pas été modifiés.',
    'missing_api_key' => 'Aucune clé API n\'est configurée pour le fournisseur de devises. Les taux n\'ont pas été modifiés.',
    'sync_failed' => 'La synchronisation des devises a échoué. Les taux n\'ont pas été modifiés.',
    'fx' => [
        'sync_in_progress' => 'Une autre synchronisation des devises est déjà en cours.',
        'provider_auth_failed' => 'Le fournisseur de devises a rejeté la clé API.',
        'provider_forbidden' => 'Le plan du fournisseur de devises n\'autorise pas cette requête.',
        'provider_http_error' => 'La requête vers le fournisseur de devises a échoué.',
        'provider_timeout' => 'Le fournisseur de devises n\'a pas répondu à temps.',
        'invalid_provider_document' => 'Le fournisseur de devises a renvoyé une réponse illisible.',
    ],
];
