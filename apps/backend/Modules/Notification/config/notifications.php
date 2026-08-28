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
            ],
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
