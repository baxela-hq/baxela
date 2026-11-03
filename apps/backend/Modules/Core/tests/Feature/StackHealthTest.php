<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Actions\Public\Health\ShowStackHealthAction;
use Modules\Core\Schemas\Health\StackHealthSchema;

uses(RefreshDatabase::class);

it('reports stack health without authentication', function () {
    $response = $this->getJson('api/v1/core/public/health')->assertOk();

    $health = $response->json('data');

    // Redis is environment-dependent (CI runs no Redis server): its check
    // may legitimately report failed, degrading but never downing the API.
    expect($health[StackHealthSchema::STATUS])->toBeIn(['ok', 'degraded'])
        ->and($health[StackHealthSchema::CHECKED_AT])->toBeString()
        ->and($health[StackHealthSchema::CHECKS]['database'][StackHealthSchema::STATUS])->toBe('ok')
        ->and($health[StackHealthSchema::CHECKS]['queue'][StackHealthSchema::STATUS])->toBe('ok')
        ->and($health[StackHealthSchema::CHECKS]['queue'][StackHealthSchema::DRIVER])->toBe('sync')
        ->and($health[StackHealthSchema::CHECKS]['queue'][StackHealthSchema::PENDING])->toBe(0)
        ->and($health[StackHealthSchema::CHECKS]['redis'][StackHealthSchema::STATUS])->toBeIn(['ok', 'failed']);
});

it('marks the stack down when the database is unreachable', function () {
    // Probed at the action level against a dedicated broken connection:
    // corrupting the default connection would poison every later DB
    // consumer in the process, tests included.
    config(['database.connections.health_broken' => [
        'driver' => 'sqlite',
        'database' => '/nonexistent-dir/health.sqlite',
    ]]);

    $health = app(ShowStackHealthAction::class)->handle(connection: 'health_broken');

    expect($health[StackHealthSchema::STATUS])->toBe(StackHealthSchema::STATUS_DOWN)
        ->and($health[StackHealthSchema::CHECKS]['database'][StackHealthSchema::STATUS])->toBe('failed')
        ->and($health[StackHealthSchema::CHECKS]['database'][StackHealthSchema::ERROR])->toBeString();
});

it('degrades without redis but goes down under strict mode', function () {
    config(['database.redis.client' => 'bogus_client']);

    $this->getJson('api/v1/core/public/health')
        ->assertOk()
        ->assertJsonPath('data.'.StackHealthSchema::STATUS, StackHealthSchema::STATUS_DEGRADED)
        ->assertJsonPath('data.'.StackHealthSchema::CHECKS.'.redis.'.StackHealthSchema::STATUS, 'failed');

    $this->getJson('api/v1/core/public/health?strict=1')
        ->assertStatus(503)
        ->assertJsonPath('data.'.StackHealthSchema::STATUS, StackHealthSchema::STATUS_DOWN);
});
