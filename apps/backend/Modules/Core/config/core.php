<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Implemented UI locales
    |--------------------------------------------------------------------------
    |
    | The ONLY language codes that ship locale files (admin + storefront).
    | Keep in sync with apps/storefront/i18n/routing.ts and
    | apps/admin/public/locales/. A language row can be activated in the
    | admin panel only when its code is listed here — the same
    | implemented-drivers idea the Payment module uses for gateways.
    | Update this list in the same PR that adds a new locale's files.
    |
    */
    'implemented_locales' => [
        'en',
        'fa',
        'de',
        'fr',
        'es',
        'ar',
    ],
];
