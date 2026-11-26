<?php

return [
    '401' => 'Unauthorized',
    '403' => 'Forbidden',
    '404' => 'Resource not found',
    '422' => 'Validation failed',
    '429' => 'Too many requests. Please try again later.',
    '500' => 'Something went wrong',

    'tenant' => [
        'creation_failed' => 'Der Shop konnte nicht erstellt werden',
        'update_failed' => 'Der Shop konnte nicht aktualisiert werden',
        'slug_taken' => 'Diese Shop-Adresse ist bereits vergeben',
        'status_change_failed' => 'Der Status des Shops konnte nicht geändert werden',
        'deletion_failed' => 'Der Shop konnte nicht gelöscht werden',
    ],

    'domain' => [
        'attachment_failed' => 'Die Domain konnte nicht mit dem Shop verknüpft werden',
        'release_failed' => 'Die Domain konnte nicht freigegeben werden',
        'in_use' => 'Diese Domain gehört zu einem anderen Shop',
        'already_attached' => 'Diese Domain ist bereits mit dem Shop verknüpft',
        'cooling_off' => 'Diese Domain wurde kürzlich freigegeben und kann noch nicht mit einem anderen Shop verknüpft werden',
    ],

    'onboarding' => [
        'limit_reached' => 'Du besitzt bereits die maximale Anzahl an Shops',
        'failed' => 'Der Shop konnte nicht erstellt werden',
    ],
];
