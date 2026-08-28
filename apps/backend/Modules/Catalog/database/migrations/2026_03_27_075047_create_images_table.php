<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Image\ImageCollectionEnum;
use Modules\Catalog\Schemas\Image\ImageSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(ImageSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(ImageSchema::PRODUCT_ID)->constrained(ProductSchema::TABLE)->onDelete('cascade');
            $table->foreignId(ImageSchema::VARIANT_ID)->nullable()->constrained(VariantSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(ImageSchema::MEDIA_ID)->index();
            $table->enum(ImageSchema::COLLECTION, ImageCollectionEnum::cases())->nullable();
            $table->string(ImageSchema::URL, 500);
            $table->unsignedTinyInteger(ImageSchema::POSITION)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ImageSchema::TABLE);
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
};
