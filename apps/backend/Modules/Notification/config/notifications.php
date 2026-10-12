<?php

use Modules\Notification\Services\Notification\Builders\DatabasePayloadBuilder;
use Modules\Notification\Services\Notification\Builders\EmailPayloadBuilder;
use Modules\Notification\Services\Notification\Builders\TelegramPayloadBuilder;
use Modules\Notification\Services\Notification\Builders\WebPushPayloadBuilder;
use Modules\Notification\Services\Notification\Channels\DatabaseChannel;
use Modules\Notification\Services\Notification\Channels\EmailChannel;
use Modules\Notification\Services\Notification\Channels\TelegramChannel;
use Modules\Notification\Services\Notification\Channels\WebPushChannel;
use Modules\Notification\Services\Notification\Templates\BladeTemplateEngine;
use Modules\Notification\Services\Notification\Templates\LocaleTemplateEngine;

return [
    'channels' => [
        'database' => DatabaseChannel::class,
        'email' => EmailChannel::class,
        'webpush' => WebPushChannel::class,
        'telegram' => TelegramChannel::class,
    ],
    'builders' => [
        'database' => DatabasePayloadBuilder::class,
        'email' => EmailPayloadBuilder::class,
        'webpush' => WebPushPayloadBuilder::class,
        'telegram' => TelegramPayloadBuilder::class,
    ],

    'notifications' => [
        'auth' => [
            'user' => [
                'otp_code' => [
                    'user' => ['email'],
                ],
            ],
        ],
        'contact' => [
            'message' => [
                'created' => [
                    'admin' => ['email'],
                ],
            ],
        ],
        'content' => [
            'post_comment' => [
                'created' => [
                    'admin' => ['database', 'email'],
                ],
                'approved' => [
                    'user' => ['database', 'email'],
                ],
            ],
        ],
        'order' => [
            'order' => [
                'created' => [
                    'admin' => ['database', 'email', 'webpush', 'telegram'],
                    'user' => ['database', 'email', 'webpush', 'telegram'],
                ],
                'paid' => [
                    'user' => ['database', 'email', 'webpush', 'telegram'],
                ],
                'shipped' => [
                    'user' => ['database', 'email', 'webpush', 'telegram'],
                ],
                'completed' => [
                    'user' => ['database', 'webpush', 'telegram'],
                ],
                'cancelled' => [
                    'user' => ['database', 'email', 'webpush', 'telegram'],
                ],
            ],
        ],
        'payment' => [
            'payment' => [
                'succeeded' => [
                    'admin' => ['database', 'webpush', 'telegram'],
                ],
                'failed' => [
                    'user' => ['database', 'webpush', 'telegram'],
                    'admin' => ['database', 'webpush', 'telegram'],
                ],
            ],
        ],
        'support' => [
            'ticket' => [
                'created' => [
                    'admin' => ['database', 'email'],
                ],
                'replied' => [
                    'admin' => ['database', 'email'],
                ],
                'answered' => [
                    'user' => ['database', 'email', 'webpush'],
                ],
            ],
        ],
    ],

    // Recipients for audience="admin" email notifications; a comma-separated
    // list of addresses, defaulting to the mail sender address.
    'admin_recipients' => [
        'email' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_NOTIFICATION_EMAILS', env('MAIL_FROM_ADDRESS', 'hello@example.com')))))),

        // Database notifications fan out to these user ids. Defaults to all
        // active staff (users holding any role) via the access gateway; set
        // ADMIN_NOTIFICATION_USER_IDS to restrict delivery to a fixed list.
        'database' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_NOTIFICATION_USER_IDS', ''))))),
    ],

    // OS-level browser notifications (Web Push via VAPID). Generate a key
    // pair once with `php artisan notification:generate-vapid`. Without
    // keys the channel no-ops with a warning.
    'webpush' => [
        'vapid' => [
            'subject' => env('VAPID_SUBJECT', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
            'public_key' => env('VAPID_PUBLIC_KEY'),
            'private_key' => env('VAPID_PRIVATE_KEY'),
        ],
    ],

    // Telegram bot delivery. Create the bot once with @BotFather and set
    // the token; without it the channel no-ops with a warning. Register
    // the webhook (against APP_URL) with `php artisan
    // notification:telegram-webhook`; for local dev without a public URL
    // use `php artisan notification:telegram-poll` instead.
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'rate_limit' => [
            'webhook' => env('NOTIFICATION_WEBHOOK_RATE_LIMIT', 60),
        ],
    ],

    'templates' => [
        // Key: template engine's name
        // Value: Full class name of the channel implementation
        'blade' => BladeTemplateEngine::class,
        'locale' => LocaleTemplateEngine::class,
        // Add other channels here
    ],

];
