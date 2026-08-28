<?php

namespace Modules\Payment\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Payment\Models\Payment;

class PaymentDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);

        Payment::factory()->count(5)->create();
    }
}
