<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Cache\Repository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Contracts\Events\System\QueueBacklogDetectedEvent;
use Throwable;

/**
 * Scheduled queue-depth monitor (gap-B3 D6).
 *
 * This command owns BOTH detection and recovery: `QueueBusy` fires only when
 * a queue crosses into busy and never on the way down, so a bare monitor +
 * listener pair cannot clear alert state by itself. Per configured target:
 *
 *  - depth above threshold: a fresh crossing (atomic cache add wins) fires
 *    QueueBacklogDetectedEvent; while the depth stays above, re-checks are
 *    suppressed by the state key;
 *  - depth at or below threshold: the state key is cleared so the next
 *    crossing re-alerts.
 *
 * Cache failures are fail-open: dedup is best-effort and must never silently
 * suppress alerts (gap-B3 D6). The state key TTL makes the suppression a
 * "dedup + maximum suppression period" — a stalled scheduler re-alerts when
 * the key expires.
 */
class QueueCheckBacklogCommand extends Command
{
    protected $signature = 'core:queue-check-backlog';

    protected $description = 'Check configured queue depths against their alert thresholds and manage backlog alert state';

    public function handle(Repository $cache): int
    {
        $threshold = (int) config('core.queue.backlog.threshold', 100);
        $targets = config('core.queue.backlog.targets') ?: [['connection' => null, 'queue' => null]];

        foreach ($targets as $target) {
            $connection = (string) ($target['connection'] ?? config('queue.default'));
            $queue = (string) ($target['queue'] ?? config("queue.connections.{$connection}.queue", 'default'));

            $this->checkTarget($cache, $connection, $queue, $threshold);
        }

        return self::SUCCESS;
    }

    private function checkTarget(Repository $cache, string $connection, string $queue, int $threshold): void
    {
        $size = (int) Queue::connection($connection)->size($queue);
        $stateKey = $this->stateKey($connection, $queue);

        if ($size > $threshold) {
            $freshCrossing = true;

            try {
                // Atomic: concurrent runs cannot both win the crossing.
                $freshCrossing = $cache->add($stateKey, 1, $this->stateTtlSeconds());
            } catch (Throwable $e) {
                // Fail-open: an unusable cache must not suppress alerts.
                Log::warning('Backlog alert-state cache unavailable; dedup is best-effort.', [
                    'connection' => $connection,
                    'queue' => $queue,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($freshCrossing) {
                Log::warning('Queue depth above alert threshold.', [
                    'connection' => $connection,
                    'queue' => $queue,
                    'size' => $size,
                    'threshold' => $threshold,
                ]);

                Event::dispatch(new QueueBacklogDetectedEvent($connection, $queue, $size, $threshold));
            }

            return;
        }

        try {
            $cache->forget($stateKey);
        } catch (Throwable $e) {
            Log::warning('Backlog alert-state cache unavailable; recovery state left uncleared.', [
                'connection' => $connection,
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function stateKey(string $connection, string $queue): string
    {
        return "core:queue:backlog:{$connection}:{$queue}";
    }

    private function stateTtlSeconds(): int
    {
        return max(1, (int) config('core.queue.backlog.state_ttl_hours', 24)) * 3600;
    }
}
