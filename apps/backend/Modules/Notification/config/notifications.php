<?php

use Modules\Notification\Services\Notification\Builders\DatabasePayloadBuilder;
use Modules\Notification\Services\Notification\Builders\EmailPayloadBuilder;
use Modules\Notification\Services\Notification\Builders\WebPushPayloadBuilder;
use Modules\Notification\Services\Notification\Channels\DatabaseChannel;
use Modules\Notification\Services\Notification\Channels\EmailChannel;
use Modules\Notification\Services\Notification\Channels\WebPushChannel;
use Modules\Notification\Services\Notification\Templates\BladeTemplateEngine;
use Modules\Notification\Services\Notification\Templates\LocaleTemplateEngine;

return [
    'channels' => [
        'database' => DatabaseChannel::class,
        'email' => EmailChannel::class,
        'webpush' => WebPushChannel::class,
    ],
    'builders' => [
        'database' => DatabasePayloadBuilder::class,
        'email' => EmailPayloadBuilder::class,
        'webpush' => WebPushPayloadBuilder::class,
    ],

    'notifications' => [
        'auth' => [
            'user' => [
                'signed_in' => [
                    'user' => ['database', 'email', 'webpush'],
                    'admin' => ['database', 'webpush'],
                ],
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
        'order' => [
            'order' => [
                'created' => [
                    'admin' => ['database', 'email', 'webpush'],
                    'user' => ['database', 'email', 'webpush'],
                ],
                'paid' => [
                    'user' => ['database', 'email', 'webpush'],
                ],
                'shipped' => [
                    'user' => ['database', 'email', 'webpush'],
                ],
                'completed' => [
                    'user' => ['database', 'webpush'],
                ],
                'cancelled' => [
                    'user' => ['database', 'email', 'webpush'],
                ],
            ],
        ],
        'payment' => [
            'payment' => [
                'succeeded' => [
                    'admin' => ['database', 'webpush'],
                ],
                'failed' => [
                    'user' => ['database', 'webpush'],
                    'admin' => ['database', 'webpush'],
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

    'templates' => [
        // Key: template engine's name
        // Value: Full class name of the channel implementation
        'blade' => BladeTemplateEngine::class,
        'locale' => LocaleTemplateEngine::class,
        // Add other channels here
    ],

];
