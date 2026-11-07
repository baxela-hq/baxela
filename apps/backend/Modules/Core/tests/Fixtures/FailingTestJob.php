<?php

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;

/**
 * Queue-visibility test fixture: always throws, so a worker run moves it to
 * failed_jobs with a genuine framework payload. Used by the retry end-to-end
 * test and the JobFailed alert tests (gap-B3).
 */
class FailingTestJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function handle(): void
    {
        throw new RuntimeException('FailingTestJob always fails — queue visibility test fixture.');
    }
}
