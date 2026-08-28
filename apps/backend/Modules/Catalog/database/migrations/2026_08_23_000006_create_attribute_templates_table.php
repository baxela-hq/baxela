<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(AttributeTemplateSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(AttributeTemplateSchema::TITLE);
            $table->text(AttributeTemplateSchema::DESCRIPTION)->nullable();
            $table->boolean(AttributeTemplateSchema::IS_ACTIVE)->default(true)->index();
            $table->unsignedSmallInteger(AttributeTemplateSchema::POSITION)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(AttributeTemplateSchema::TABLE);
    }
};
