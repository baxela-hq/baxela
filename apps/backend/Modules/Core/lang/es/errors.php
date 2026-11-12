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

    // FX rate auto-sync (Part E) — sanitized, stable codes.
    'fx_no_default_currency' => 'No hay moneda predeterminada configurada.',
    'base_mismatch' => 'El proveedor devolvió tarifas de una moneda base diferente. Las tarifas no se modificaron.',
    'stale_rates' => 'El proveedor devolvió tarifas obsoletas. Las tarifas no se modificaron.',
    'invalid_timestamp' => 'El proveedor devolvió una marca de tiempo no válida. Las tarifas no se modificaron.',
    'missing_api_key' => 'No hay clave API configurada para el proveedor de divisas. Las tarifas no se modificaron.',
    'sync_failed' => 'La sincronización de divisas falló. Las tarifas no se modificaron.',
    'fx' => [
        'sync_in_progress' => 'Ya se está ejecutando otra sincronización de divisas.',
        'provider_auth_failed' => 'El proveedor de divisas rechazó la clave API.',
        'provider_forbidden' => 'El plan del proveedor de divisas no permite esta solicitud.',
        'provider_http_error' => 'La solicitud al proveedor de divisas falló.',
        'provider_timeout' => 'El proveedor de divisas no respondió a tiempo.',
        'invalid_provider_document' => 'El proveedor de divisas devolvió una respuesta ilegible.',
    ],
];
