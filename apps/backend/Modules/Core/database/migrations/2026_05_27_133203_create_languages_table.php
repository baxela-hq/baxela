<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Language\LanguageSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(LanguageSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->char(LanguageSchema::LOCALE, 5)->unique();
            $table->string(LanguageSchema::NAME);
            $table->string(LanguageSchema::NATIVE_NAME)->nullable();
            $table->char(LanguageSchema::CODE, 2);
            $table->char(LanguageSchema::CODE3, 3);
            $table->boolean(LanguageSchema::IS_RTL)->default(false);
            $table->boolean(LanguageSchema::IS_ACTIVE)->default(false);
            $table->boolean(LanguageSchema::IS_DEFAULT)->default(false);
            $table->unsignedSmallInteger(LanguageSchema::POSITION)->nullable();
            $table->string(LanguageSchema::DATE_FORMAT)->nullable();
            $table->string(LanguageSchema::TIME_FORMAT)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(LanguageSchema::TABLE);
    }
};
