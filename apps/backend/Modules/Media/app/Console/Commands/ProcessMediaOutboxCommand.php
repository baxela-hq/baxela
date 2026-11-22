<?php

namespace Modules\Media\Console\Commands;

use Illuminate\Console\Command;
use Modules\Media\Support\MediaPublicationProcessor;

/**
 * The media outbox worker (postgres-migration §3.3): claims staged
 * publications and completes them through the fenced terminal transition.
 * Scheduled every minute (withoutOverlapping); inline post-commit
 * invocation after each upload is the latency optimization — this command
 * is the durable recovery path (crash-window safe by outbox construction).
 */
final class ProcessMediaOutboxCommand extends Command
{
    protected $signature = 'media:outbox:process {--limit= : Maximum entries per run}';

    protected $description = 'Publish staged media files through the fenced outbox protocol';

    public function handle(MediaPublicationProcessor $processor): int
    {
        [$completed, $deferred] = $processor->process(
            $this->option('limit') !== null ? (int) $this->option('limit') : null,
        );

        if ($completed > 0 || $deferred > 0) {
            $this->info(sprintf('Media outbox: %d published, %d deferred.', $completed, $deferred));
        }

        return $deferred === 0 ? self::SUCCESS : self::FAILURE;
    }
}
