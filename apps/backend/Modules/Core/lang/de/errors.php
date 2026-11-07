<?php

return [
    '401' => 'Nicht autorisiert',
    '403' => 'Zugriff verweigert',
    '404' => 'Ressource nicht gefunden',
    '422' => 'Validierung fehlgeschlagen',
    '429' => 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.',
    '500' => 'Etwas ist schiefgelaufen',
    'internal_server_error' => 'Etwas ist schiefgelaufen',

    // Failed-jobs admin surface (gap-B3) — agent-drafted, pending owner review.
    'failed_jobs' => [
        'not_found' => 'Der fehlgeschlagene Job wurde nicht gefunden',
        'search_too_short' => 'Die Ausnahmesuche muss mindestens :min Zeichen lang sein',
    ],
];
