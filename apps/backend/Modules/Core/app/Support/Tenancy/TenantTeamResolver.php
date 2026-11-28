<?php

namespace Modules\Core\Support\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * The spatie permission team IS the tenant context (multi-tenancy §5):
 * every pivot write and every role/permission lookup resolves against the
 * active tenant — no set/restore juggling anywhere, because there is
 * nothing to juggle. Outside MODE_TENANT the team is null, which is
 * exactly the state where no store-scoped pivot write may happen.
 *
 * `setPermissionsTeamId` is accepted and ignored: the context is the
 * single authority for the team id.
 */
final class TenantTeamResolver implements PermissionsTeamResolver
{
    public function setPermissionsTeamId(int|string|Model|null $id): void {}

    public function getPermissionsTeamId(): ?int
    {
        return app(TenantContext::class)->tenantId();
    }
}
