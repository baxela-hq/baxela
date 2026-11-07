<?php

return [
    '401' => 'Unauthorized',
    '403' => 'Forbidden',
    '404' => 'Resource not found',
    '422' => 'Validation failed',
    '429' => 'Too many requests. Please try again later.',
    '500' => 'Something went wrong',
    'internal_server_error' => 'Something went wrong',

    // Failed-jobs admin surface (gap-B3) — nested keys resolve via
    // ExceptionHelper (core.failed_jobs.* → core::errors.failed_jobs.*).
    'failed_jobs' => [
        'not_found' => 'The failed job was not found',
        'search_too_short' => 'The exception search must be at least :min characters',
    ],
];
