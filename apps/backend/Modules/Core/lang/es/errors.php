<?php

return [
    '401' => 'No autorizado',
    '403' => 'Prohibido',
    '404' => 'Recurso no encontrado',
    '422' => 'Error de validación',
    '429' => 'Demasiadas solicitudes. Inténtalo de nuevo más tarde.',
    '500' => 'Algo salió mal',
    'internal_server_error' => 'Algo salió mal',

    // Failed-jobs admin surface (gap-B3) — agent-drafted, pending owner review.
    'failed_jobs' => [
        'not_found' => 'El trabajo fallido no se encontró',
        'search_too_short' => 'La búsqueda de excepciones debe tener al menos :min caracteres',
    ],
];
