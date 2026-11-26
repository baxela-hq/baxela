<?php

namespace Modules\Tenant\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\NewAccessToken;
use Modules\Auth\Models\User;
use Modules\Core\Exceptions\BaseException;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantDomain;
use Modules\Tenant\Models\TenantMembership;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;

trait HelperTrait
{
    public function baseUrl(string $endpoint = ''): string
    {
        return 'api/v1/tenant'.$endpoint;
    }

    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /** An active account without any role: a customer, or a store member holding no permissions. */
    public function plainUser(): User
    {
        return User::factory()->active()->create();
    }

    public function createTenant(
        string $slug,
        ?string $domain = null,
        string $status = TenantSchema::STATUS_ACTIVE,
    ): Tenant {
        /** @var Tenant $tenant */
        $tenant = Tenant::query()->create([
            TenantSchema::NAME => ucfirst($slug),
            TenantSchema::SLUG => $slug,
            TenantSchema::EMAIL => $slug.'@example.test',
            TenantSchema::STATUS => $status,
        ]);

        if ($domain !== null) {
            TenantDomain::query()->create([
                TenantDomainSchema::TENANT_ID => $tenant->getKey(),
                TenantDomainSchema::DOMAIN => $domain,
                TenantDomainSchema::STATUS => TenantDomainSchema::STATUS_VERIFIED,
                TenantDomainSchema::IS_PRIMARY => true,
                TenantDomainSchema::VERIFIED_AT => now(),
            ]);
        }

        return $tenant;
    }

    /** An active membership, written the way the seeder and onboarding write it: under the tenant's own context. */
    public function addMembership(User $user, Tenant $tenant, bool $owner = false): TenantMembership
    {
        return app(TenantContext::class)->runForTenant(
            (int) $tenant->getKey(),
            fn () => TenantMembership::query()->create([
                TenantMembershipSchema::USER_ID => $user->getKey(),
                TenantMembershipSchema::STATUS => TenantMembershipSchema::STATUS_ACTIVE,
                TenantMembershipSchema::IS_OWNER => $owner,
                TenantMembershipSchema::JOINED_AT => now(),
            ]),
        );
    }

    /** A tenant-bound machine token — what the designated issuance path produces — as a Bearer value. */
    public function machineToken(User $user, int $tenantId): string
    {
        return $this->mintMachineToken($user, $tenantId)->plainTextToken;
    }

    /** The mint itself, so a test can keep the token row to revoke or expire it. */
    public function mintMachineToken(User $user, ?int $tenantId): NewAccessToken
    {
        $token = $user->createToken('machine', ['products.read']);

        if ($tenantId !== null) {
            $token->accessToken->forceFill([TenantColumn::TENANT_ID => $tenantId])->save();
        }

        return $token;
    }

    /** A production-like tenancy config: no fallback hosts, and a central admin API host. */
    public function productionLikeTenancy(): void
    {
        config([
            'core.tenancy.fallback_hosts' => '',
            'core.tenancy.central_hosts' => 'api.example.test',
        ]);
    }

    /**
     * Probe routes on the real `api` group that echo the tenant context
     * the middleware established. Names follow the audience grammar.
     */
    public function registerProbeRoutes(): void
    {
        Route::middleware('api')->prefix('api/v1/tenant-probe')->name('api.tenant.public.probe.')->group(function (): void {
            Route::get('/context', fn () => [
                'tenant_id' => app(TenantContext::class)->tenantId(),
                'mode' => app(TenantContext::class)->mode(),
            ])->name('context');
        });
    }

    /**
     * Runs the operation expecting exactly the given error code, and hands
     * the exception back for further checks. Pest's toThrow() closure form
     * only asserts the exception's class — it discards what the closure
     * returns — so which refusal occurred needs this.
     *
     * @template TException of BaseException
     *
     * @param  callable(): mixed  $operation
     * @param  class-string<TException>  $exceptionClass
     * @return TException
     */
    public function assertThrowsCode(
        callable $operation,
        string $exceptionClass,
        string $code,
        ?int $httpStatus = null,
    ): BaseException {
        try {
            $operation();
        } catch (BaseException $exception) {
            expect($exception)->toBeInstanceOf($exceptionClass)
                ->and($exception->code)->toBe($code)
                ->and($exception->httpStatus)->toBe($httpStatus ?? $exception->httpStatus);

            return $exception;
        }

        $this->fail('Expected '.$exceptionClass.' ['.$code.'], nothing was thrown.');
    }

    public function probeContext(): string
    {
        return '/api/v1/tenant-probe/context';
    }

    /**
     * A URL on the given host — the only way a test sets the Host. The
     * test client builds the request from the URL and overwrites any
     * `Host` header with the URL's own host.
     */
    public function onHost(string $host, string $uri = ''): string
    {
        return 'http://'.$host.$uri;
    }
}
