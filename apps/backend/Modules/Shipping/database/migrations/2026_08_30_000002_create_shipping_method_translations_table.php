<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shipping\Schemas\Method\MethodSchema;
use Modules\Shipping\Schemas\Method\MethodTranslationSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(MethodTranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(MethodTranslationSchema::METHOD_ID)
                ->constrained(MethodSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(MethodTranslationSchema::LANGUAGE_ID)->nullable();
            $table->string(MethodTranslationSchema::NAME);
            $table->text(MethodTranslationSchema::DESCRIPTION)->nullable();
            $table->timestamps();

            $table->unique([MethodTranslationSchema::METHOD_ID, MethodTranslationSchema::LANGUAGE_ID]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(MethodTranslationSchema::TABLE);
    }
};
