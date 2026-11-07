<?php

namespace Modules\Webhooks\Console\Commands;

use Illuminate\Console\Command;
use Modules\Webhooks\Actions\Webhooks\DispatchDueDeliveriesAction;

class DispatchWebhookDeliveriesCommand extends Command
{
    protected $signature = 'webhooks:dispatch-deliveries';

    protected $description = 'Enqueue due webhook deliveries (gap-C2 D3 sweep — part of the correctness model)';

    public function handle(DispatchDueDeliveriesAction $action): int
    {
        $count = $action->handle();

        $this->info("Enqueued {$count} webhook deliveries.");

        return self::SUCCESS;
    }
}
