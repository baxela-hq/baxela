<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(TaxClassSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(TaxClassSchema::NAME);
            // The two single-flag invariants (exactly one default, exactly
            // one shipping class) are enforced transactionally by the admin
            // actions and guaranteed by the seeder
            $table->boolean(TaxClassSchema::IS_DEFAULT)->default(false)->index();
            $table->boolean(TaxClassSchema::IS_SHIPPING)->default(false)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(TaxClassSchema::TABLE);
    }
};
