<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Core\Models\FailedJob;
use Modules\Core\Schemas\FailedJob\FailedJobSchema;
use Modules\Core\Support\FailedJob\FailedJobPayloadPresenter;
use Modules\Core\Tests\Feature\HelperTrait;
use Modules\Core\Tests\Fixtures\FailingTestJob;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * A payload envelope shaped like the framework's real queue payload (uuid,
 * displayName, job, data.command with a valid serialized command blob). The
 * display/redaction paths here never parse the blob; the retry end-to-end
 * runs against a genuinely failing fixture job instead of this row.
 */
function failedJobRow(array $overrides = []): array
{
    $uuid = (string) Str::uuid();

    return array_merge([
        FailedJobSchema::UUID => $uuid,
        FailedJobSchema::CONNECTION => 'database',
        FailedJobSchema::QUEUE => 'default',
        FailedJobSchema::PAYLOAD => json_encode([
            'uuid' => $uuid,
            'displayName' => 'App\Jobs\SendOrderConfirmation',
            'job' => 'Illuminate\Queue\CallQueuedHandler@call',
            'maxTries' => null,
            'api_token' => 'tok_abc123',
            'data' => [
                'commandName' => 'App\Jobs\SendOrderConfirmation',
                'command' => serialize((object) ['orderId' => 1024, 'mailPassword' => 's3cr3t-v4']),
            ],
        ]),
        FailedJobSchema::EXCEPTION => "Illuminate\Queue\MaxAttemptsExceededException: App\Jobs\SendOrderConfirmation has been attempted too many times. SMTP relay unreachable at 2026-11-07 09:14:00 — connection timed out after retrying mail.example.com three times.",
        FailedJobSchema::FAILED_AT => now(),
    ], $overrides);
}

it('lists failed jobs newest first with job name and exception excerpt', function () {
    $this->actingAs($this->superAdminUser());

    $newer = FailedJob::query()->create(failedJobRow([FailedJobSchema::FAILED_AT => now()->subMinutes(1)]));
    $older = FailedJob::query()->create(failedJobRow([
        FailedJobSchema::FAILED_AT => now()->subHour(),
        FailedJobSchema::QUEUE => 'notifications',
    ]));

    $response = $this->getJson($this->adminUrl('/failed-jobs'))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($response->json('data.0.uuid'))->toBe($newer->{FailedJobSchema::UUID})
        ->and($response->json('data.0.job'))->toBe('App\Jobs\SendOrderConfirmation')
        ->and($response->json('data.0.queue'))->toBe('default')
        ->and($response->json('data.1.uuid'))->toBe($older->{FailedJobSchema::UUID})
        // list rows carry the excerpt, never the full exception nor the payload
        ->and(strlen((string) $response->json('data.0.exception')))->toBeLessThanOrEqual(300)
        ->and($response->json('data.0.payload'))->toBeNull();
});

it('filters failed jobs by queue, uuid and exception substring', function () {
    $this->actingAs($this->superAdminUser());

    $match = FailedJob::query()->create(failedJobRow());
    FailedJob::query()->create(failedJobRow([
        FailedJobSchema::QUEUE => 'notifications',
        FailedJobSchema::EXCEPTION => 'Telegram API unreachable',
    ]));

    $this->getJson($this->adminUrl('/failed-jobs?filter[queue]=notifications'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.exception', 'Telegram API unreachable');

    $this->getJson($this->adminUrl('/failed-jobs?filter[uuid]='.$match->{FailedJobSchema::UUID}))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $match->{FailedJobSchema::UUID});

    $this->getJson($this->adminUrl('/failed-jobs?filter[exception_search]=mail.example.com'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $match->{FailedJobSchema::UUID});
});

it('rejects exception searches shorter than the configured minimum', function () {
    $this->actingAs($this->superAdminUser());

    // locale-agnostic: the store's boot locale decides the rendered language
    $expected = __('core::errors.failed_jobs.search_too_short', ['min' => 3]);

    $this->getJson($this->adminUrl('/failed-jobs?filter[exception_search]=ab'))
        ->assertStatus(422)
        ->assertJsonPath('message', __('core::errors.422'))
        ->assertJsonPath('errors.exception_search.0', $expected);
});

it('shows a failed job with the full exception and a redacted payload', function () {
    $this->actingAs($this->superAdminUser());

    $row = FailedJob::query()->create(failedJobRow());

    $response = $this->getJson($this->adminUrl('/failed-jobs/'.$row->{FailedJobSchema::UUID}))->assertOk();

    $payload = $response->json('data.payload');

    expect($response->json('data.job'))->toBe('App\Jobs\SendOrderConfirmation')
        ->and(str_contains($response->json('data.exception'), 'SMTP relay unreachable'))->toBeTrue()
        // the serialized command blob never leaves the backend — the
        // mailPassword inside it is unreachable by construction (D3)
        ->and($payload['data']['command'])->toBe(FailedJobPayloadPresenter::COMMAND_PLACEHOLDER)
        ->and($payload['data']['commandName'])->toBe('App\Jobs\SendOrderConfirmation')
        // secret-ish JSON keys are masked by the denylist
        ->and($payload['api_token'])->toBe(FailedJobPayloadPresenter::REDACTED)
        ->and($payload['displayName'])->toBe('App\Jobs\SendOrderConfirmation');
});

it('responds 404 with a specific message for an unknown uuid', function () {
    $this->actingAs($this->superAdminUser());

    // render-time messages localize by Accept-Language (bootstrap render
    // callback); the header pins the response language deterministically.
    $headers = ['Accept-Language' => 'en'];

    $this->getJson($this->adminUrl('/failed-jobs/'.Str::uuid()), $headers)
        ->assertNotFound()
        ->assertJsonPath('message', 'The failed job was not found');

    $this->postJson($this->adminUrl('/failed-jobs/'.Str::uuid().'/retry'), [], $headers)
        ->assertNotFound()
        ->assertJsonPath('message', 'The failed job was not found');

    $this->deleteJson($this->adminUrl('/failed-jobs/'.Str::uuid()), $headers)
        ->assertNotFound()
        ->assertJsonPath('message', 'The failed job was not found');
});

it('retry re-queues a genuinely failed job and removes the failed row', function () {
    $this->actingAs($this->superAdminUser());

    // Real worker failure: the fixture job lands in failed_jobs with a
    // genuine framework payload, on the database connection (the test env's
    // sync default only affects new dispatches, not this row).
    Queue::connection('database')->push(new FailingTestJob);
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true]);

    expect(FailedJob::query()->count())->toBe(1);
    $row = FailedJob::query()->firstOrFail();

    // Retry pushes the raw payload back onto the connection recorded on the
    // row (database), regardless of the test env's sync default.
    $this->postJson($this->adminUrl('/failed-jobs/'.$row->{FailedJobSchema::UUID}.'/retry'))
        ->assertNoContent();

    expect(FailedJob::query()->where(FailedJobSchema::UUID, $row->{FailedJobSchema::UUID})->exists())->toBeFalse()
        ->and(Queue::connection('database')->size('default'))->toBe(1);
});

it('forget permanently removes a failed row', function () {
    $this->actingAs($this->superAdminUser());

    $row = FailedJob::query()->create(failedJobRow());

    $this->deleteJson($this->adminUrl('/failed-jobs/'.$row->{FailedJobSchema::UUID}))
        ->assertNoContent();

    expect(FailedJob::query()->count())->toBe(0);
});

it('rejects the failed-job endpoints without any failed-job permission', function () {
    $this->actingAs(User::factory()->active()->create());

    $row = FailedJob::query()->create(failedJobRow());

    $this->getJson($this->adminUrl('/failed-jobs'))->assertForbidden();
    $this->getJson($this->adminUrl('/failed-jobs/'.$row->{FailedJobSchema::UUID}))->assertForbidden();
    $this->postJson($this->adminUrl('/failed-jobs/'.$row->{FailedJobSchema::UUID}.'/retry'))->assertForbidden();
    $this->deleteJson($this->adminUrl('/failed-jobs/'.$row->{FailedJobSchema::UUID}))->assertForbidden();
});

it('allows show-only admins to read but not to retry or forget', function () {
    $user = User::factory()->active()->create();
    foreach (['core.admin.failed-jobs.list', 'core.admin.failed-jobs.show'] as $name) {
        $user->givePermissionTo(
            Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => GuardsEnum::WEB->value,
            ])
        );
    }
    $this->actingAs($user);

    $row = FailedJob::query()->create(failedJobRow());

    $this->getJson($this->adminUrl('/failed-jobs'))->assertOk();
    $this->getJson($this->adminUrl('/failed-jobs/'.$row->{FailedJobSchema::UUID}))->assertOk();
    $this->postJson($this->adminUrl('/failed-jobs/'.$row->{FailedJobSchema::UUID}.'/retry'))->assertForbidden();
    $this->deleteJson($this->adminUrl('/failed-jobs/'.$row->{FailedJobSchema::UUID}))->assertForbidden();
});
