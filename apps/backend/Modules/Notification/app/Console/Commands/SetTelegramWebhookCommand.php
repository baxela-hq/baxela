<?php

namespace Modules\Notification\Console\Commands;

use Illuminate\Console\Command;
use Modules\Notification\Gateways\TelegramClient;
use Modules\Notification\Schemas\Module;
use Throwable;

class SetTelegramWebhookCommand extends Command
{
    protected $signature = 'notification:telegram-webhook {--remove : Delete the webhook instead of setting it}';

    protected $description = 'Register (or remove) the Telegram bot webhook pointing at this app';

    public function handle(TelegramClient $telegram): int
    {
        if ($telegram->isConfigured() === false) {
            $this->error('TELEGRAM_BOT_TOKEN is not set — create the bot with @BotFather first.');

            return self::FAILURE;
        }

        try {
            if ($this->option('remove')) {
                $telegram->deleteWebhook();
                $this->info('Telegram webhook removed. Run `notification:telegram-poll` if you want to keep receiving updates locally.');

                return self::SUCCESS;
            }

            $secret = (string) config('notification.notifications.telegram.webhook_secret');

            if ($secret === '') {
                $this->error('TELEGRAM_WEBHOOK_SECRET is not set — Telegram would deliver updates unsigned. Generate one (e.g. `echo $(openssl rand -hex 16)`) and set it.');

                return self::FAILURE;
            }

            $url = rtrim((string) config('app.url'), '/')
                .'/api/v1/'.Module::ROUTE_PREFIX
                .'/webhook/telegram';

            $telegram->setWebhook($url, $secret);
            $this->info("Telegram webhook set to {$url}");
            $this->comment('The URL must be publicly reachable over HTTPS (ports 443/80/88/8443). For local dev use `notification:telegram-poll` instead.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Telegram API call failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
