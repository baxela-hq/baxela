<?php

namespace Modules\Tenant\Actions\User\Onboarding;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Tenant\TenantProvisionedEvent;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Exceptions\Onboarding\LimitReachedException;
use Modules\Tenant\Exceptions\Onboarding\ProvisioningFailedException;
use Modules\Tenant\Exceptions\Tenant\SlugTakenException;
use Modules\Tenant\Exceptions\TenantDomain\InUseException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantDomain;
use Modules\Tenant\Models\TenantMembership;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;
use Modules\Tenant\Support\UniqueViolation;
use Throwable;

/**
 * Self-service store provisioning (multi-tenancy §6.5, Category 3).
 *
 * Never enters central mode and is not in the operation registry: the
 * request context stays INACTIVE while the tenant and domain rows are
 * written (tenancy metadata), and the tenant-owned rows — the creator's
 * owner membership — are written under runForTenant() with the id this
 * transaction just created, so the caller never supplies a tenant. One
 * transaction: either the store exists whole, or nothing does.
 *
 * The per-user quota is checked under a row lock on the user: a
 * transaction alone does not stop two concurrent requests from both
 * passing the limit (§6.5, F4).
 *
 * Later phases add to this path: the owner role assignment (Phase 3) and
 * the store's default settings and language/currency links (Phase 7).
 */
class ProvisionTenantAction
{
    public function __construct(private readonly AccessGatewayInterface $access) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Authenticatable $user, array $data): Tenant
    {
        $userId = (int) $user->getAuthIdentifier();
        $slug = (string) ($data[TenantSchema::SLUG] ?? '');
        $name = (string) ($data[TenantSchema::NAME] ?? '');
        $limit = max(1, (int) config('core.tenancy.onboarding.max_tenants_per_user'));

        try {
            $tenant = DB::transaction(function () use ($userId, $slug, $name, $limit): Tenant {
                // Serializes every onboarding of the same user.
                $this->access->lockUser($userId);

                $owned = TenantMembership::query()
                    ->where(TenantMembershipSchema::USER_ID, $userId)
                    ->where(TenantMembershipSchema::IS_OWNER, true)
                    ->count();

                if ($owned >= $limit) {
                    throw new LimitReachedException(meta: ['limit' => $limit]);
                }

                /** @var Tenant $tenant */
                $tenant = Tenant::query()->create([
                    TenantSchema::NAME => $name,
                    TenantSchema::SLUG => $slug,
                    // The store's contact address starts as its owner's —
                    // read through the Auth gateway, never from its models.
                    TenantSchema::EMAIL => $this->access->getUserEmailsByIds([$userId])[$userId] ?? null,
                    TenantSchema::STATUS => TenantSchema::STATUS_ACTIVE,
                ]);

                $this->attachSubdomain($tenant, $slug);

                // The context is restored to INACTIVE in `finally`.
                return app(TenantContext::class)->runForTenant(
                    (int) $tenant->getKey(),
                    function () use ($tenant, $userId): Tenant {
                        TenantMembership::query()->create([
                            TenantMembershipSchema::USER_ID => $userId,
                            TenantMembershipSchema::STATUS => TenantMembershipSchema::STATUS_ACTIVE,
                            TenantMembershipSchema::IS_OWNER => true,
                            TenantMembershipSchema::JOINED_AT => now(),
                        ]);

                        // The store's default roles (multi-tenancy §8 Phase 3)
                        // and the owner's role assignment: the pivot attach
                        // runs under the new tenant's spatie team, set and
                        // restored inside the gateway (§6.5 B7).
                        $ownerRoleId = $this->access->seedTenantRoles((int) $tenant->getKey());
                        $this->access->assignTenantRole($userId, (int) $tenant->getKey(), $ownerRoleId);

                        return $tenant;
                    },
                );
            });
        } catch (LimitReachedException $e) {
            throw $e;
        } catch (UniqueConstraintViolationException $e) {
            // The transaction is already aborted on PostgreSQL: decide
            // from the violation itself, no follow-up query.
            if (UniqueViolation::on($e, TenantSchema::SLUG)) {
                throw new SlugTakenException;
            }

            if (UniqueViolation::on($e, TenantDomainSchema::DOMAIN)) {
                // The `<slug>` subdomain is another store's verified domain.
                throw new InUseException;
            }

            throw new ProvisioningFailedException;
        } catch (Throwable $e) {
            report($e);

            throw new ProvisioningFailedException;
        }

        event(new TenantProvisionedEvent(
            (int) $tenant->getKey(),
            (string) $tenant->getAttribute(TenantSchema::SLUG),
            (string) $tenant->getAttribute(TenantSchema::NAME),
            $userId,
        ));

        return $tenant;
    }

    private function attachSubdomain(Tenant $tenant, string $slug): void
    {
        $base = trim((string) config('core.tenancy.onboarding.subdomain_base'));

        if ($base === '') {
            return;
        }

        TenantDomain::query()->create([
            TenantDomainSchema::TENANT_ID => $tenant->getKey(),
            TenantDomainSchema::DOMAIN => $slug.'.'.strtolower($base),
            TenantDomainSchema::STATUS => TenantDomainSchema::STATUS_VERIFIED,
            TenantDomainSchema::IS_PRIMARY => true,
            TenantDomainSchema::VERIFIED_AT => now(),
        ]);
    }
}
