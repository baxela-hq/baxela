<?php

namespace Modules\Core\Contracts\Events\System;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

/**
 * Fired when the scheduled queue-depth monitor observes a configured target
 * crossing its depth threshold (gap-B3 D6) — a fresh crossing only: the
 * monitor deduplicates while the depth stays above the threshold and re-fires
 * after recovery, so consumers get crossings, not every five-minute sample.
 */
class QueueBacklogDetectedEvent extends AbstractBaseEvent
{
    public function __construct(
        public string $connection,
        public string $queue,
        public int $size,
        public int $threshold,
    ) {}
}
