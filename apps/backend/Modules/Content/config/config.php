<?php

use Modules\Content\Transfer\ContentTransfer;

return [
    'name' => 'Content',

    'sitemap' => [
        // Seconds the public sitemap feed stays cached; feeds are pure
        // reads, so a stale window only delays lastmod/slug updates.
        'cache_ttl' => 3600,
    ],

    // Data-transfer standard registration — picked up by the Transfer
    // module's registry (see docs/transfer.md).
    'transfer' => [
        'class' => ContentTransfer::class,
    ],
];
