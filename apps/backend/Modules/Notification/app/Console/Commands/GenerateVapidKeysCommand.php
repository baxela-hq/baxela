<?php

namespace Modules\Notification\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeysCommand extends Command
{
    protected $signature = 'notification:generate-vapid';

    protected $description = 'Generate a VAPID key pair for web push notifications';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->info('VAPID key pair generated. Add the values to your environment:');
        $this->line(sprintf('VAPID_PUBLIC_KEY=%s', $keys['publicKey']));
        $this->line(sprintf('VAPID_PRIVATE_KEY=%s', $keys['privateKey']));
        $this->newLine();
        $this->comment('Keys are generated once and reused — regenerating invalidates every existing push subscription.');

        return self::SUCCESS;
    }
}
