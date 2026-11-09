<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Extension\ExtensionSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(ExtensionSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(ExtensionSchema::SLUG)->unique();
            $table->boolean(ExtensionSchema::ENABLED)->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ExtensionSchema::TABLE);
    }
};
