<?php

namespace Modules\Content\Database\Seeders;

use Illuminate\Database\Seeder;

class ContentDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            PageSeeder::class,
        ]);
    }
}
