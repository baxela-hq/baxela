<?php

namespace Modules\Core\Actions\Admin\FailedJob;

/**
 * Permanently deletes a failed job record (gap-B3 D4: irreversible). The
 * framework failer's forget() returns bool — a false here means the row was
 * removed concurrently between the existence check and the delete, which is
 * the same end state the caller asked for: idempotent success, no error.
 */
class ForgetFailedJobAction extends AbstractFailedJobAction
{
    public function handle(string $uuid): void
    {
        $this->findFailedJob($uuid);

        app('queue.failer')->forget($uuid);
    }
}
