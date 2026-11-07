<?php

namespace Modules\Core\Actions\Admin\FailedJob;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Modules\Core\Exceptions\FailedJob\FailedJobRetryException;
use Modules\Core\Schemas\FailedJob\FailedJobSchema;

/**
 * Re-queues a failed job for another execution attempt — not "recovery": the
 * job may fail again and may repeat its side effects (gap-B3 D4).
 *
 * The pre-check happens in the abstract base; the framework command then
 * dispatches JobRetryRequested, pushes the payload back onto the connection
 * recorded on the failed row, and removes the row itself. Because the command
 * exits 0 even when nothing matched (verified against the installed Laravel),
 * the post-condition — the row is gone — is asserted instead of the exit code.
 */
class RetryFailedJobAction extends AbstractFailedJobAction
{
    public function handle(string $uuid): void
    {
        $this->findFailedJob($uuid);

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        if ($this->model->query()->where(FailedJobSchema::UUID, $uuid)->exists()) {
            Log::warning('queue:retry left the failed row behind.', [
                'uuid' => $uuid,
                'output' => trim(Artisan::output()),
            ]);

            throw new FailedJobRetryException;
        }
    }
}
