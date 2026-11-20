<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Actions\Public\Auth\SignInAction;
use Modules\Auth\Exceptions\AccountNotActivatedException;
use Modules\Auth\Http\Requests\Public\Auth\SignInRequest;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;

uses(RefreshDatabase::class);

it('creates inert accounts for unknown emails and reuses them (C6)', function () {
    $gateway = app(AccessGatewayInterface::class);

    $id = $gateway->ensureUserByEmail('  New.Customer@Example.COM ');

    $account = User::query()->findOrFail($id);
    expect($account->{UserSchema::EMAIL})->toBe('new.customer@example.com')
        ->and($account->is_active)->toBeFalse()
        ->and($account->{UserSchema::EMAIL_VERIFIED_AT})->toBeNull()
        // A random unguessable password — nobody can pass a credential
        // check with a known value (C6).
        ->and($account->{UserSchema::PASSWORD})->not->toBe('')
        // No roles: the import never grants authority.
        ->and($account->roles()->count())->toBe(0);

    // Convergence: the same address (any casing) resolves to the SAME id.
    expect($gateway->ensureUserByEmail('new.customer@example.com'))->toBe($id)
        ->and(User::query()->count())->toBe(1);
});

it('cannot sign in with an imported account until Auth activates it (C6/F22)', function () {
    $gateway = app(AccessGatewayInterface::class);

    // Import-style placeholder account.
    $id = $gateway->ensureUserByEmail('imported@example.com');

    // The HTTP gate: the placeholder's password is an unguessable random
    // string, so any credential guess fails at Auth's credential check.
    $this->postJson('api/v1/auth/public/auth/signin', [
        'email' => 'imported@example.com',
        'password' => 'any-guessable-value',
    ])->assertStatus(401);

    // Even with a KNOWN password (the only way a real activation flow
    // could ever set one), the account is refused at the ACTIVATION gate
    // — Auth owns that gate (AbstractAction), not the import (C6/F22).
    $account = User::query()->findOrFail($id);
    $account->{UserSchema::PASSWORD} = 'correct-horse-battery-staple';
    $account->save();

    $action = $this->app->make(SignInAction::class);
    expect(fn () => $action->handle(
        SignInRequest::create('', 'POST', [
            'email' => 'imported@example.com',
            'password' => 'correct-horse-battery-staple',
        ])
    ))->toThrow(AccountNotActivatedException::class);

    // Still zero tokens: the placeholder never authenticated.
    expect(DB::table('personal_access_tokens')->where('tokenable_id', $id)->count())->toBe(0);
});
