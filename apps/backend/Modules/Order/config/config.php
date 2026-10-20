<?php

return [
    'name' => 'Order',

    'returns' => [
        // Days after payment (falling back to placement) within which a
        // customer may request a return for a shipped/completed order.
        'window_days' => 14,
    ],
];
