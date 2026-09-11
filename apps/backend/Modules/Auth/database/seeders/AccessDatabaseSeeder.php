<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Actions\SyncPermissionsAction;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;

class AccessDatabaseSeeder extends Seeder
{
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

        User::query()->first()?->assignRole($role);
    }
}
