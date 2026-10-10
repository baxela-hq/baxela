<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Content\Schemas\FeaturedItem\FeaturedItemSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(FeaturedItemSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(FeaturedItemSchema::FEATUREDABLE_TYPE);
            $table->unsignedBigInteger(FeaturedItemSchema::FEATUREDABLE_ID);
            $table->unsignedSmallInteger(FeaturedItemSchema::POSITION)->default(0);
            $table->timestamps();

            $table->unique([FeaturedItemSchema::FEATUREDABLE_TYPE, FeaturedItemSchema::FEATUREDABLE_ID]);
            $table->index([FeaturedItemSchema::FEATUREDABLE_TYPE, FeaturedItemSchema::POSITION]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(FeaturedItemSchema::TABLE);
    }
};
