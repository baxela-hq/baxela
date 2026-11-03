<?php

namespace Modules\Core\Actions\Public\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Modules\Core\Schemas\Health\StackHealthSchema;
use Throwable;

class ShowStackHealthAction
{
    /**
     * Stack liveness for uptime monitors. The database is critical — its
     * failure takes the store down (HTTP 503). Redis and the queue are
     * reported and only degrade the status: realtime goes dark but checkout
     * keeps serving. `strict` escalates any failed check to down, for
     * monitors that should page on a degraded stack too. The optional
     * connection name probes a non-default database (ops/testing).
     *
     * @return array<string, mixed>
     */
    public function handle(bool $strict = false, ?string $connection = null): array
    {
        $checks = [
            'database' => $this->check(fn () => DB::connection($connection)->select('select 1')),
            'redis' => $this->check(fn () => Redis::connection()->ping()),
            'queue' => $this->queueCheck(),
        ];

        return [
            StackHealthSchema::STATUS => $this->overallStatus($checks, $strict),
            StackHealthSchema::CHECKED_AT => now()->toIso8601String(),
            StackHealthSchema::CHECKS => $checks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queueCheck(): array
    {
        $driver = (string) config('queue.default');

        return $this->check(fn () => [
            StackHealthSchema::DRIVER => $driver,
            StackHealthSchema::PENDING => Queue::size(),
            StackHealthSchema::FAILED => app('queue.failer')->count(),
        ]);
    }

    /**
     * Run a probe; a healthy one may attach extra detail, a failing one
     * records why. Nothing a probe throws escapes as a 500.
     *
     * @param  callable(): mixed  $probe
     * @return array<string, mixed>
     */
    private function check(callable $probe): array
    {
        try {
            $extras = $probe();

            return array_merge(
                [StackHealthSchema::STATUS => StackHealthSchema::STATUS_OK],
                is_array($extras) ? $extras : [],
            );
        } catch (Throwable $e) {
            return [
                StackHealthSchema::STATUS => 'failed',
                StackHealthSchema::ERROR => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $checks
     */
    private function overallStatus(array $checks, bool $strict): string
    {
        $failed = array_filter(
            $checks,
            fn (array $check) => $check[StackHealthSchema::STATUS] !== StackHealthSchema::STATUS_OK
        );

        if ($failed === []) {
            return StackHealthSchema::STATUS_OK;
        }

        $databaseDown = ($failed['database'] ?? null) !== null;

        return ($strict || $databaseDown)
            ? StackHealthSchema::STATUS_DOWN
            : StackHealthSchema::STATUS_DEGRADED;
    }
}
