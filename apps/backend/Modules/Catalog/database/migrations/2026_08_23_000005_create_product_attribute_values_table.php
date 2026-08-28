<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;
use Modules\Catalog\Schemas\Product\ProductAttributeValueSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(ProductAttributeValueSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(ProductAttributeValueSchema::PRODUCT_ID)
                ->constrained(ProductSchema::TABLE)->onDelete('cascade');
            $table->foreignId(ProductAttributeValueSchema::ATTRIBUTE_ID)
                ->constrained(AttributeSchema::TABLE)->onDelete('cascade');
            $table->foreignId(ProductAttributeValueSchema::ATTRIBUTE_VALUE_ID)->nullable()
                ->constrained(AttributeValueSchema::TABLE)->onDelete('cascade');
            $table->string(ProductAttributeValueSchema::TEXT_VALUE)->nullable();
            $table->decimal(ProductAttributeValueSchema::NUMBER_VALUE, 12, 2)->unsigned()->nullable();
            $table->boolean(ProductAttributeValueSchema::BOOLEAN_VALUE)->nullable();

            // Scalar rows keep attribute_value_id NULL (NULLs never collide in unique
            // indexes), so this dedupes select/multiselect rows without blocking scalars.
            // Explicit names: MySQL caps identifiers at 64 chars and the defaults overflow.
            $table->unique([ProductAttributeValueSchema::PRODUCT_ID,
                ProductAttributeValueSchema::ATTRIBUTE_VALUE_ID],
                ProductAttributeValueSchema::PRODUCT_ID.'_'.ProductAttributeValueSchema::ATTRIBUTE_VALUE_ID.'_unique');

            $table->index([ProductAttributeValueSchema::PRODUCT_ID,
                ProductAttributeValueSchema::ATTRIBUTE_ID],
                ProductAttributeValueSchema::PRODUCT_ID.'_'.ProductAttributeValueSchema::ATTRIBUTE_ID.'_index');
            $table->index([ProductAttributeValueSchema::ATTRIBUTE_ID,
                ProductAttributeValueSchema::ATTRIBUTE_VALUE_ID],
                ProductAttributeValueSchema::ATTRIBUTE_ID.'_'.ProductAttributeValueSchema::ATTRIBUTE_VALUE_ID.'_index');
            $table->index([ProductAttributeValueSchema::ATTRIBUTE_ID,
                ProductAttributeValueSchema::NUMBER_VALUE],
                ProductAttributeValueSchema::ATTRIBUTE_ID.'_'.ProductAttributeValueSchema::NUMBER_VALUE.'_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ProductAttributeValueSchema::TABLE);
    }
};
