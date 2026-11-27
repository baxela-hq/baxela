<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Option\OptionSchema;
use Modules\Catalog\Schemas\Option\OptionTranslationSchema;
use Modules\Core\Schemas\Shared\TenantColumn;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(OptionSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->unsignedSmallInteger(OptionSchema::POSITION)->nullable();
            $table->timestamps();
        });

        Schema::create(OptionTranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->foreignId(OptionTranslationSchema::OPTION_ID)
                ->constrained(OptionSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(OptionTranslationSchema::LANGUAGE_ID);
            $table->string(OptionTranslationSchema::TITLE);
            $table->string(OptionTranslationSchema::SLUG);
            $table->timestamps();

            $table->unique([OptionTranslationSchema::OPTION_ID, OptionTranslationSchema::LANGUAGE_ID]);
            $table->unique([TenantColumn::TENANT_ID, OptionTranslationSchema::LANGUAGE_ID, OptionTranslationSchema::SLUG]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(OptionTranslationSchema::TABLE);
        Schema::dropIfExists(OptionSchema::TABLE);
    }
};
