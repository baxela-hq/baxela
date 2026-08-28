<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;

class CatalogDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $coreGateway = App::make(CoreGatewayInterface::class);

        $languageId = $coreGateway->getLanguageIdByCode(App::currentLocale());
        if (! $languageId) {
            $this->command->error('Language not found for default language: '.App::currentLocale());
            $this->command->error('Run this seeder after running the core seeder.');

            return;
        }

        $this->call([
            OptionSeeder::class,
            AttributeSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
        ]);
    }
}
