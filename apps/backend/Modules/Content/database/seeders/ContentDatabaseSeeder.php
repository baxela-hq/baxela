<?php

namespace Modules\Content\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Content\Models\Page;

class ContentDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);

        Page::factory()
            ->withTranslations()
            ->count(10)
            ->create();
    }
}
