<?php

use Illuminate\Cache\Repository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Contracts\Events\System\QueueBacklogDetectedEvent;
use Modules\Core\Tests\Fixtures\FailingTestJob;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('core.queue.backlog', [
        'threshold' => 1,
        'targets' => [['connection' => 'database', 'queue' => 'default']],
        'state_ttl_hours' => 24,
    ]);
});

it('fires the backlog event once when depth crosses the threshold', function () {
    Event::fake([QueueBacklogDetectedEvent::class]);

    Queue::connection('database')->push(new FailingTestJob);
    Queue::connection('database')->push(new FailingTestJob);

    $this->artisan('core:queue-check-backlog')->assertSuccessful();

    // Re-checks above the threshold are suppressed by the state key.
    $this->artisan('core:queue-check-backlog')->assertSuccessful();

    Event::assertDispatched(QueueBacklogDetectedEvent::class, 1);
});

it('fires the event with the monitored target, size and threshold', function () {
    Event::fake([QueueBacklogDetectedEvent::class]);

    Queue::connection('database')->push(new FailingTestJob);
    Queue::connection('database')->push(new FailingTestJob);
    Queue::connection('database')->push(new FailingTestJob);

    $this->artisan('core:queue-check-backlog')->assertSuccessful();

    Event::assertDispatched(QueueBacklogDetectedEvent::class, 1, fn (QueueBacklogDetectedEvent $e) => $e->connection === 'database'
        && $e->queue === 'default'
        && $e->size === 3
        && $e->threshold === 1);
});

it('clears alert state on recovery and re-alerts on the next crossing', function () {
    Event::fake([QueueBacklogDetectedEvent::class]);

    Queue::connection('database')->push(new FailingTestJob);
    Queue::connection('database')->push(new FailingTestJob);
    $this->artisan('core:queue-check-backlog')->assertSuccessful(); // crossing #1

    // pop() only reserves a row — size() still counts it; drain for real.
    DB::table('jobs')->delete();
    $this->artisan('core:queue-check-backlog')->assertSuccessful(); // recovery

    Queue::connection('database')->push(new FailingTestJob);
    Queue::connection('database')->push(new FailingTestJob);
    $this->artisan('core:queue-check-backlog')->assertSuccessful(); // crossing #2

    Event::assertDispatchedTimes(QueueBacklogDetectedEvent::class, 2);
});

it('monitors every configured target, not only the default queue', function () {
    Event::fake([QueueBacklogDetectedEvent::class]);

    config()->set('core.queue.backlog.targets', [
        ['connection' => 'database', 'queue' => 'default'],
        ['connection' => 'database', 'queue' => 'emails'],
    ]);

    Queue::connection('database')->pushOn('emails', new FailingTestJob);
    Queue::connection('database')->pushOn('emails', new FailingTestJob);

    $this->artisan('core:queue-check-backlog')->assertSuccessful();

    Event::assertDispatched(QueueBacklogDetectedEvent::class, 1, fn (QueueBacklogDetectedEvent $e) => $e->queue === 'emails' && $e->size === 2);
});

it('writes the alert-state key with the configured suppression TTL', function () {
    Event::fake([QueueBacklogDetectedEvent::class]);
    Queue::connection('database')->push(new FailingTestJob);
    Queue::connection('database')->push(new FailingTestJob);

    // The command injects a Repository resolved through the manager's
    // driver(), so the mock chain must end in a Repository mock.
    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('add')
        ->once()
        ->with('core:queue:backlog:database:default', 1, 24 * 3600)
        ->andReturn(true);
    $repository->shouldReceive('forget')->zeroOrMoreTimes();
    Cache::shouldReceive('driver')->zeroOrMoreTimes()->andReturn($repository);

    $this->artisan('core:queue-check-backlog')->assertSuccessful();

    Event::assertDispatched(QueueBacklogDetectedEvent::class, 1);
});

it('fails open when the cache is unavailable — the alert still fires', function () {
    Event::fake([QueueBacklogDetectedEvent::class]);
    Queue::connection('database')->push(new FailingTestJob);
    Queue::connection('database')->push(new FailingTestJob);

    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('add')
        ->once()
        ->andThrow(new RuntimeException('cache down'));
    $repository->shouldReceive('forget')->zeroOrMoreTimes();
    Cache::shouldReceive('driver')->zeroOrMoreTimes()->andReturn($repository);

    $this->artisan('core:queue-check-backlog')->assertSuccessful();

    Event::assertDispatched(QueueBacklogDetectedEvent::class, 1);
});

it('registers both schedules with the configured retention and overlap protection', function () {
    config()->set('core.queue.failed_jobs.retention_days', 30);

    $events = collect(app(Schedule::class)->events());

    $backlog = $events->first(fn ($event) => str_contains((string) $event->command, 'core:queue-check-backlog'));
    $prune = $events->first(fn ($event) => str_contains((string) $event->command, 'queue:prune-failed --hours=720'));

    expect($backlog)->not->toBeNull()
        ->and($backlog->withoutOverlapping)->toBeTrue()
        ->and($backlog->expression)->toBe('*/5 * * * *')
        ->and($prune)->not->toBeNull()
        ->and($prune->expression)->toBe('0 0 * * *');
});
