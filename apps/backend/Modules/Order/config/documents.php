<?php

return [
    // Document numbering (gap-B1, D3): prefixes are cosmetic at rendering
    // time and stored on the document — changing them affects only future
    // numbers. Sequences restart per (type, year), year resolved through
    // the store-timezone resolver.
    'prefixes' => [
        'invoice' => 'INV',
        'credit_note' => 'CN',
    ],

    // Generation lease (D7): a `generating` claim older than this many
    // minutes is considered abandoned by the stale-generation sweep.
    'generation_lease_minutes' => 10,
];
