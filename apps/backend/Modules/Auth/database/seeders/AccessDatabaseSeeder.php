<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Actions\SyncPermissionsAction;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Auth\Support\AssignsPlatformRole;

class AccessDatabaseSeeder extends Seeder
{
    use AssignsPlatformRole;

    /**
     * Run the database seeds.
     *
     * @throws \Exception
     */
    public function run(): void
    {
        app(SyncPermissionsAction::class)();

        $role = Role::query()->firstOrCreate([
            'name' => Role::SUPER_ADMIN,
            'guard_name' => GuardsEnum::WEB->value,
        ]);

        // The pivot attach carries the default tenant's team id (§5); the
        // bypass itself is team-agnostic, so this is bookkeeping only.
        User::query()->get()->each(fn (User $user) => $this->attachPlatformRole($user, $role));
    }
}
