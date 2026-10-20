<?php

return [
    'class' => [
        'creation_failed' => 'Tax class creation failed',
        'update_failed' => 'Tax class update failed',
        'delete_default' => 'The default tax class cannot be deleted; designate another default first',
        'in_use' => 'The tax class is still assigned to products and cannot be deleted',
    ],
    'rate' => [
        'creation_failed' => 'Tax rate creation failed',
        'update_failed' => 'Tax rate update failed',
    ],
    'configuration' => [
        'invalid_class' => 'A product references a tax class that no longer exists; the checkout cannot proceed',
        'no_default' => 'No default tax class is configured',
    ],
];
