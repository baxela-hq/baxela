<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateGroupSchema;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(AttributeTemplateGroupSchema::TABLE, function (Blueprint $table) {
            $table->foreignId(AttributeTemplateGroupSchema::TEMPLATE_ID)
                ->constrained(AttributeTemplateSchema::TABLE)->onDelete('cascade');

            $table->foreignId(AttributeTemplateGroupSchema::GROUP_ID)
                ->constrained(AttributeGroupSchema::TABLE)->onDelete('cascade');

            $table->unsignedSmallInteger(AttributeTemplateGroupSchema::POSITION)->nullable();

            $table->primary([AttributeTemplateGroupSchema::TEMPLATE_ID,
                AttributeTemplateGroupSchema::GROUP_ID]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(AttributeTemplateGroupSchema::TABLE);
    }
};
