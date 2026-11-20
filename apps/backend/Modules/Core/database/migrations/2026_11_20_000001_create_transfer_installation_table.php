<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\TransferInstallation\TransferInstallationSchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TransferInstallationSchema::TABLE, function (Blueprint $table) {
            // Single-row table: the row's id is fixed (C1), so first write
            // wins and concurrent boots adopt the winner via the unique
            // uuid index.
            $table->id();
            $table->string(TransferInstallationSchema::UUID, 36)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TransferInstallationSchema::TABLE);
    }
};
