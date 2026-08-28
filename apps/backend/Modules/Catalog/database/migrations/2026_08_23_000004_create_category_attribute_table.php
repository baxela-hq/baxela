<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\Category\CategoryAttributeSchema;
use Modules\Catalog\Schemas\Category\CategorySchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(CategoryAttributeSchema::TABLE, function (Blueprint $table) {
            $table->foreignId(CategoryAttributeSchema::CATEGORY_ID)
                ->constrained(CategorySchema::TABLE)->onDelete('cascade');

            $table->foreignId(CategoryAttributeSchema::ATTRIBUTE_ID)
                ->constrained(AttributeSchema::TABLE)->onDelete('cascade');

            $table->unsignedSmallInteger(CategoryAttributeSchema::POSITION)->nullable();

            $table->primary([CategoryAttributeSchema::CATEGORY_ID, CategoryAttributeSchema::ATTRIBUTE_ID]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CategoryAttributeSchema::TABLE);
    }
};
