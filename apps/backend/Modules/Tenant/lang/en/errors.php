<?php

return [
    '401' => 'Unauthorized',
    '403' => 'Forbidden',
    '404' => 'Resource not found',
    '422' => 'Validation failed',
    '429' => 'Too many requests. Please try again later.',
    '500' => 'Something went wrong',

    'tenant' => [
        'creation_failed' => 'The store could not be created',
        'update_failed' => 'The store could not be updated',
        'slug_taken' => 'This store address is already taken',
        'status_change_failed' => 'The store status could not be changed',
        'deletion_failed' => 'The store could not be deleted',
    ],
    'domain' => [
        'attachment_failed' => 'The domain could not be attached to the store',
        'release_failed' => 'The domain could not be released',
        'in_use' => 'This domain belongs to another store',
        'already_attached' => 'This domain is already attached to the store',
        'cooling_off' => 'This domain was released recently and cannot be attached to another store yet',
    ],
    'onboarding' => [
        'limit_reached' => 'You already own the maximum number of stores',
        'failed' => 'The store could not be created',
    ],
];
