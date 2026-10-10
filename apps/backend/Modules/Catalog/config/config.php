<?php

return [
    'name' => 'Catalog',

    'sitemap' => [
        // Seconds the public sitemap feed stays cached; feeds are pure
        // reads, so a stale window only delays lastmod/slug updates.
        'cache_ttl' => 3600,
    ],
];
