<?php

use Modules\Notification\Services\Notification\Builders\DatabasePayloadBuilder;
use Modules\Notification\Services\Notification\Builders\EmailPayloadBuilder;
use Modules\Notification\Services\Notification\Channels\DatabaseChannel;
use Modules\Notification\Services\Notification\Channels\EmailChannel;
use Modules\Notification\Services\Notification\Templates\BladeTemplateEngine;
use Modules\Notification\Services\Notification\Templates\LocaleTemplateEngine;

return [
    'channels' => [
        'database' => DatabaseChannel::class,
        'email' => EmailChannel::class,
    ],
    'builders' => [
        'database' => DatabasePayloadBuilder::class,
        'email' => EmailPayloadBuilder::class,
    ],

    'notifications' => [
        'auth' => [
            'user' => [
                'signed_in' => [
                    'user' => ['database', 'email'],
                    'admin' => ['database'],
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

    'templates' => [
        // Key: template engine's name
        // Value: Full class name of the channel implementation
        'blade' => BladeTemplateEngine::class,
        'locale' => LocaleTemplateEngine::class,
        // Add other channels here
    ],

];
