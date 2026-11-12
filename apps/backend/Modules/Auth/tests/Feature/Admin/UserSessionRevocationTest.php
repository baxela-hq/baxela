<?php

use Illuminate\Auth\AuthManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Modules\Audit\Models\AuditLog;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\TwoFactorChallenge\TwoFactorChallengeSchema;
use Modules\Auth\Support\TwoFactor\TwoFactorChallengeService;
use Modules\Auth\Schemas\TwoFactorChallenge\TwoFactorChallengeTypeEnum;
use Modules\Auth\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function s2TargetUser(): User
{
    return User::factory()->create([
        'password' => 'Passw0rd!',
        'is_active' => true,
    ]);
}

function s2SignInToken(User $user, string $device): string
{
    return test()->postJson(test()->baseUrl('/public/auth/signin'), [
        'email' => $user->email,
        'password' => 'Passw0rd!',
        'device_name' => $device,
    ])->assertOk()->json('data.token');
}

/**
 * Same guard-caching caveat as SessionTest: forget guards between calls
 * so a revoked token cannot ride an earlier request's resolved user.
 */
function s2JsonAs(string $token, string $method, string $endpoint): TestResponse
{
    app(AuthManager::class)->forgetGuards();

    $headers = ['Authorization' => "Bearer {$token}"];

    if (strtolower($method) === 'get') {
        return test()->getJson(test()->baseUrl($endpoint), $headers);
    }

    return test()->{strtolower($method).'Json'}(test()->baseUrl($endpoint), [], $headers);
}

it('lists another user\'s sessions as super-admin', function () {
    $this->actingAs($this->superAdminUser());
    $target = s2TargetUser();
    s2SignInToken($target, 'laptop');
    s2SignInToken($target, 'phone');

    $response = $this->getJson($this->baseUrl("/admin/users/{$target->id}/sessions"))
        ->assertOk();

    $sessions = collect($response->json('data'))->keyBy('name');

    expect($sessions)->toHaveCount(2)
        // the sessions belong to the target, not the acting admin
        ->and($sessions['phone']['is_current'])->toBeFalse()
        ->and($sessions['laptop']['is_current'])->toBeFalse();
});

it('force-revokes one of another user\'s sessions and audit-logs it', function () {
    $admin = $this->superAdminUser();
    $this->actingAs($admin);
    $target = s2TargetUser();
    $laptop = s2SignInToken($target, 'laptop');
    $phone = s2SignInToken($target, 'phone');

    $laptopSessionId = $target->tokens()->where('name', 'laptop')->value('id');

    $this->deleteJson($this->baseUrl("/admin/users/{$target->id}/sessions/{$laptopSessionId}"))
        ->assertNoContent();

    s2JsonAs($laptop, 'get', '/user/account/me')->assertUnauthorized();
    s2JsonAs($phone, 'get', '/user/account/me')->assertOk();

    $audit = AuditLog::query()
        ->where('entity', 'user_session')
        ->where('entity_id', $target->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->action)->toBe('revoked')
        ->and($audit->module)->toBe('auth')
        ->and($audit->user_id)->toBe($admin->id)
        ->and($audit->properties['session_id'])->toBe((int) $laptopSessionId);
});

it('404s when the session id belongs to a different user', function () {
    $this->actingAs($this->superAdminUser());
    $target = s2TargetUser();
    $other = s2TargetUser();
    $otherToken = s2SignInToken($other, 'other-device');

    $foreignSessionId = $other->tokens()->value('id');

    $this->deleteJson($this->baseUrl("/admin/users/{$target->id}/sessions/{$foreignSessionId}"))
        ->assertNotFound();

    // nothing was revoked — the other user's session still works
    s2JsonAs($otherToken, 'get', '/user/account/me')->assertOk();
});

it('force-revokes every session, invalidates 2FA challenges, and audit-logs it', function () {
    $admin = $this->superAdminUser();
    $this->actingAs($admin);
    $target = s2TargetUser();
    $laptop = s2SignInToken($target, 'laptop');
    $phone = s2SignInToken($target, 'phone');

    // An in-flight sign-in challenge for the target.
    $rawChallenge = app(TwoFactorChallengeService::class)->issue(
        $target,
        TwoFactorChallengeTypeEnum::SIGN_IN,
    );

    $this->deleteJson($this->baseUrl("/admin/users/{$target->id}/sessions"))
        ->assertNoContent();

    s2JsonAs($laptop, 'get', '/user/account/me')->assertUnauthorized();
    s2JsonAs($phone, 'get', '/user/account/me')->assertUnauthorized();

    // D9: the pending challenge died with the sessions.
    $challenge = \Modules\Auth\Models\TwoFactorChallenge::find(hash('sha256', $rawChallenge));
    expect($challenge->{TwoFactorChallengeSchema::CONSUMED_AT})->not->toBeNull();

    $audit = AuditLog::query()
        ->where('entity', 'user_sessions')
        ->where('entity_id', $target->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->action)->toBe('revoked')
        ->and($audit->properties['revoked_count'])->toBe(2)
        ->and($audit->properties['revoked_by'])->toBe($admin->id);
});

it('rejects forced revocation without the permission', function () {
    $plain = User::factory()->create(['is_active' => true]);
    $this->actingAs($plain);
    $target = s2TargetUser();
    s2SignInToken($target, 'laptop');

    $this->getJson($this->baseUrl("/admin/users/{$target->id}/sessions"))->assertForbidden();
    $this->deleteJson($this->baseUrl("/admin/users/{$target->id}/sessions"))->assertForbidden();
    $this->deleteJson($this->baseUrl("/admin/users/{$target->id}/sessions/1"))->assertForbidden();
});

it('404s an unknown target user', function () {
    $this->actingAs($this->superAdminUser());

    $this->getJson($this->baseUrl('/admin/users/999999/sessions'))->assertNotFound();
    $this->deleteJson($this->baseUrl('/admin/users/999999/sessions'))->assertNotFound();
});
