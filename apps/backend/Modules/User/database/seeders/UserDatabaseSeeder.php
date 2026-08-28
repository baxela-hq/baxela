<?php

namespace Modules\User\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\User\Models\Address;
use Modules\User\Models\Profile;

class UserDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        Profile::factory()->count(10)->create();
        Address::factory()->count(10)->create();
    }
}
