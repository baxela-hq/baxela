<?php

return [
    'preference' => [
        'locked' => 'Cette notification est transactionnelle et ne peut pas être désactivée.',
    ],
    'telegram' => [
        'unconfigured' => 'Le bot Telegram n’est pas configuré.',
        'link_invalid' => 'Ce code de liaison est invalide ou a expiré. Envoyez /start au bot pour obtenir un nouveau code.',
        'chat_unreachable' => 'Le bot ne peut pas écrire dans ce chat. Envoyez d’abord /start au bot, puis réessayez.',
        'not_linked' => 'Aucun compte Telegram n’est encore lié.',
        'webhook_unauthorized' => 'Signature de webhook Telegram invalide.',
        'request_failed' => 'La requête vers l’API Telegram a échoué.',
    ],
];
