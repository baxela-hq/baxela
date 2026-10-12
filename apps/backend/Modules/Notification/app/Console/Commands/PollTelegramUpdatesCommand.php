<?php

namespace Modules\Notification\Console\Commands;

use Illuminate\Console\Command;
use Modules\Notification\Actions\Webhook\Telegram\HandleTelegramUpdateAction;
use Modules\Notification\Gateways\TelegramClient;
use Throwable;

class PollTelegramUpdatesCommand extends Command
{
    protected $signature = 'notification:telegram-poll';

    protected $description = 'Long-poll Telegram updates (local development; production uses the webhook)';

    /**
     * Long-polling must not use the webhook: Telegram allows only one
     * delivery mode, so polling with a registered webhook silently
     * receives nothing.
     */
    public function handle(TelegramClient $telegram, HandleTelegramUpdateAction $action): int
    {
        if ($telegram->isConfigured() === false) {
            $this->error('TELEGRAM_BOT_TOKEN is not set — create the bot with @BotFather first.');

            return self::FAILURE;
        }

        $this->warn('Polling mode is meant for local development (production should run `notification:telegram-webhook`).');
        $this->warn('A registered webhook disables polling — run `notification:telegram-webhook --remove` if updates never arrive.');
        $this->info('Polling bot updates — press Ctrl+C to stop.');

        set_time_limit(0);
        $offset = 0;

        while (true) {
            try {
                foreach ($telegram->getUpdates($offset) as $update) {
                    $offset = (int) $update->update_id + 1;

                    $action->handleUpdate((array) json_decode(json_encode($update), true));
                }
            } catch (Throwable $e) {
                // Transient network/API failures shouldn't kill the worker;
                // back off briefly and keep the loop alive.
                $this->error("Poll failed: {$e->getMessage()} — retrying in 5s");
                sleep(5);
            }
        }
    }
}
