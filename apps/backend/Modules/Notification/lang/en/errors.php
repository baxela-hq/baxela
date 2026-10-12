<?php

return [
    'preference' => [
        'locked' => 'This notification is transactional and cannot be turned off.',
    ],
    'telegram' => [
        'unconfigured' => 'The Telegram bot is not configured.',
        'link_invalid' => 'That linking code is invalid or has expired. Send /start to the bot for a fresh code.',
        'chat_unreachable' => 'The bot cannot message that chat. Send /start to the bot first, then try again.',
        'not_linked' => 'No Telegram account is linked yet.',
        'webhook_unauthorized' => 'Invalid Telegram webhook signature.',
        'request_failed' => 'The Telegram API request failed.',
    ],
];
