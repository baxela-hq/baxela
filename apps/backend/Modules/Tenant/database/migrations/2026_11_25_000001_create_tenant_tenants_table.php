<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tenant\Schemas\Tenant\TenantSchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TenantSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(TenantSchema::NAME);
            $table->string(TenantSchema::SLUG)->unique();
            $table->string(TenantSchema::EMAIL)->unique();
            $table->string(TenantSchema::STATUS, 20)->default(TenantSchema::STATUS_ACTIVE);
            $table->index(TenantSchema::STATUS);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TenantSchema::TABLE);
    }
};
