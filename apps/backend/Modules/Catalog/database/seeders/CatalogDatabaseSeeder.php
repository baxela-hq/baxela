<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;

class CatalogDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            OptionSeeder::class,
            AttributeSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
        ]);
    }
}
