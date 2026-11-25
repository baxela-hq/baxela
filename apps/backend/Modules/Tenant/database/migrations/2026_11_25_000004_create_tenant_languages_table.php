<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantLanguage\TenantLanguageSchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TenantLanguageSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantLanguageSchema::TENANT_ID);
            // No cross-module FK: the language is validated through the Core gateway.
            $table->unsignedBigInteger(TenantLanguageSchema::LANGUAGE_ID);
            $table->timestamps();

            $table->foreign(TenantLanguageSchema::TENANT_ID)
                ->references('id')->on(TenantSchema::TABLE)
                ->cascadeOnDelete();

            $table->unique(
                [TenantLanguageSchema::TENANT_ID, TenantLanguageSchema::LANGUAGE_ID],
                'tenant_languages_tenant_lang_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TenantLanguageSchema::TABLE);
    }
};
