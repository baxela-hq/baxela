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

    // FX rate auto-sync (Part E) — sanitized, stable codes.
    'fx_no_default_currency' => 'Es ist keine Standardwährung konfiguriert.',
    'base_mismatch' => 'Der Anbieter lieferte Kurse für eine andere Basiswährung. Kurse wurden nicht geändert.',
    'stale_rates' => 'Der Anbieter lieferte veraltete Kurse. Kurse wurden nicht geändert.',
    'invalid_timestamp' => 'Der Anbieter lieferte einen ungültigen Zeitstempel. Kurse wurden nicht geändert.',
    'missing_api_key' => 'Es ist kein API-Schlüssel für den FX-Anbieter konfiguriert. Kurse wurden nicht geändert.',
    'sync_failed' => 'Die FX-Synchronisierung ist fehlgeschlagen. Kurse wurden nicht geändert.',
    'fx' => [
        'sync_in_progress' => 'Es läuft bereits eine andere FX-Synchronisierung.',
        'provider_auth_failed' => 'Der FX-Anbieter hat den API-Schlüssel abgelehnt.',
        'provider_forbidden' => 'Der Anbieterplan erlaubt diese Anfrage nicht.',
        'provider_http_error' => 'Die Anfrage an den FX-Anbieter ist fehlgeschlagen.',
        'provider_timeout' => 'Der FX-Anbieter hat nicht rechtzeitig geantwortet.',
        'invalid_provider_document' => 'Der FX-Anbieter lieferte eine unlesbare Antwort.',
    ],

    // Extension admin surface (marketplace P5.1) — enabling requires the
    // registry row AND the Composer package; see ExtensionsException.
    'extensions' => [
        'not_installed' => 'Die Erweiterung :slug ist nicht installiert.',
    ],
];
