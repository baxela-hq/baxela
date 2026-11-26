<?php

return [
    '401' => 'Unauthorized',
    '403' => 'Forbidden',
    '404' => 'Resource not found',
    '422' => 'Validation failed',
    '429' => 'Too many requests. Please try again later.',
    '500' => 'Something went wrong',

    'tenant' => [
        'creation_failed' => 'No se pudo crear la tienda',
        'update_failed' => 'No se pudo actualizar la tienda',
        'slug_taken' => 'Esta dirección de tienda ya está ocupada',
        'status_change_failed' => 'No se pudo cambiar el estado de la tienda',
        'deletion_failed' => 'No se pudo eliminar la tienda',
    ],

    'domain' => [
        'attachment_failed' => 'No se pudo vincular el dominio a la tienda',
        'release_failed' => 'No se pudo liberar el dominio',
        'in_use' => 'Este dominio pertenece a otra tienda',
        'already_attached' => 'Este dominio ya está vinculado a la tienda',
        'cooling_off' => 'Este dominio se liberó recientemente y aún no puede vincularse a otra tienda',
    ],

    'onboarding' => [
        'limit_reached' => 'Ya tienes el número máximo de tiendas',
        'failed' => 'No se pudo crear la tienda',
    ],
];
