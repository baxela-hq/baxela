<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Models\FailedJob;
use Modules\Core\Tests\Fixtures\FailingTestJob;
use Modules\Notification\Emails\DynamicNotification;
use Modules\Notification\Models\Notification;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

const JOB_FAILED_CODE = 'system.queue.job_failed';
const BACKLOG_CODE = 'system.queue.backlog';

it('alerts admins synchronously when a queued job finally fails', function () {
    Mail::fake();

    $admin = $this->superAdminUser();

    // Real worker failure — JobFailed fires inside the worker and the sync
    // listener alerts before queue:work --once returns.
    Queue::connection('database')->push(new FailingTestJob);
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    expect(FailedJob::query()->count())->toBe(1);

    $alert = Notification::query()
        ->where('user_id', $admin->id)
        ->where('code', JOB_FAILED_CODE)
        ->where('audience', 'admin')
        ->first();

    expect($alert)->not->toBeNull()
        ->and($alert->body)->toContain('FailingTestJob')
        ->and($alert->meta['uuid'])->toBeString()
        ->and($alert->meta['queue'])->toBe('default')
        ->and($alert->meta['connection'])->toBe('database');
});

it('keeps the exception excerpt inside the inbox and out of external channels', function () {
    Mail::fake();

    $this->superAdminUser();

    Queue::connection('database')->push(new FailingTestJob);
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    // The database inbox carries the exception text.
    $inbox = Notification::query()->where('code', JOB_FAILED_CODE)->where('audience', 'admin')->first();
    expect($inbox->body)->toContain('always fails');

    // External channels (email here) carry identifiers only — never the
    // exception text, not even through the telegram/webpush data fallback.
    Mail::assertSent(DynamicNotification::class, 1);
    Mail::assertSent(DynamicNotification::class, fn (DynamicNotification $mail) => str_contains((string) $mail->subject, 'FailingTestJob')
        && ! str_contains((string) $mail->body, 'always fails'));
});

it('keeps failure handling intact when the alert dispatch throws', function () {
    Mail::fake();
    $this->superAdminUser();

    // A broken notification stack must not break the worker's failure path.
    $this->instance(NotificationDispatcherInterface::class, new class implements NotificationDispatcherInterface
    {
        public function dispatch($message): void
        {
            throw new RuntimeException('notification stack is down');
        }
    });

    Queue::connection('database')->push(new FailingTestJob);
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    expect(FailedJob::query()->count())->toBe(1)
        ->and(Notification::query()->where('code', JOB_FAILED_CODE)->count())->toBe(0);
});

it('alerts admins when the queue depth crosses its threshold', function () {
    Mail::fake();

    $admin = $this->superAdminUser();

    config()->set('core.queue.backlog', [
        'threshold' => 1,
        'targets' => [['connection' => 'database', 'queue' => 'default']],
        'state_ttl_hours' => 24,
    ]);

    Queue::connection('database')->push(new FailingTestJob);
    Queue::connection('database')->push(new FailingTestJob);

    $this->artisan('core:queue-check-backlog')->assertSuccessful();

    $alert = Notification::query()
        ->where('user_id', $admin->id)
        ->where('code', BACKLOG_CODE)
        ->where('audience', 'admin')
        ->first();

    expect($alert)->not->toBeNull()
        ->and($alert->meta['queue'])->toBe('default')
        ->and($alert->meta['size'])->toBe(2);

    Mail::assertSent(DynamicNotification::class, fn (DynamicNotification $mail) => str_contains((string) $mail->subject, 'default'));
});
