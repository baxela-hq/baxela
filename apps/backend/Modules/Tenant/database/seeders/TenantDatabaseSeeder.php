<?php

namespace Modules\Tenant\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantDomain;
use Modules\Tenant\Models\TenantMembership;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;

/**
 * The "multi-tenant by default, single-store by zero config" guarantee
 * (multi-tenancy §4.1): a fresh install ships one default tenant with the
 * loopback domain, so a single-store deployment needs no tenant setup.
 *
 * Lives in the Tenant module rather than CoreDatabaseSeeder (as the plan
 * first assumed): Core cannot reference the Tenant module, and this
 * seeder needs the Tenant models.
 *
 * The owner membership is granted here to the platform super-admins —
 * read through the Auth gateway, never by reaching into Auth's models.
 * It carries ownership semantics only; platform authorization continues to
 * derive exclusively from the NULL-team super-admin role.
 */
class TenantDatabaseSeeder extends Seeder
{
    public const string DEFAULT_SLUG = 'default';

    /** @var list<string> */
    private const array LOOPBACK_DOMAINS = ['localhost', '127.0.0.1'];

    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            [TenantSchema::SLUG => self::DEFAULT_SLUG],
            [
                TenantSchema::NAME => 'Default Store',
                TenantSchema::EMAIL => config('mail.from.address') ?? 'store@localhost',
                TenantSchema::STATUS => TenantSchema::STATUS_ACTIVE,
            ],
        );

        foreach (self::LOOPBACK_DOMAINS as $domain) {
            TenantDomain::query()->firstOrCreate(
                [TenantDomainSchema::DOMAIN => $domain],
                [
                    TenantDomainSchema::TENANT_ID => $tenant->getKey(),
                    TenantDomainSchema::STATUS => TenantDomainSchema::STATUS_VERIFIED,
                    TenantDomainSchema::IS_PRIMARY => $domain === 'localhost',
                    TenantDomainSchema::VERIFIED_AT => now(),
                ],
            );
        }

        // Seeders run in MODE_INACTIVE, and membership writes are
        // tenant-confined (§6.5 Category 2) — so the owner rows are
        // created inside the default tenant's context.
        app(TenantContext::class)->runForTenant((int) $tenant->getKey(), function () use ($tenant): void {
            foreach (app(AccessGatewayInterface::class)->adminUserIds() as $userId) {
                TenantMembership::query()->firstOrCreate([
                    TenantMembershipSchema::TENANT_ID => $tenant->getKey(),
                    TenantMembershipSchema::USER_ID => $userId,
                ], [
                    TenantMembershipSchema::STATUS => TenantMembershipSchema::STATUS_ACTIVE,
                    TenantMembershipSchema::IS_OWNER => true,
                    TenantMembershipSchema::JOINED_AT => now(),
                ]);
            }
        });
    }
}
