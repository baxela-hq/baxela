<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Core\Schemas\Shared\TenantColumn;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(FeaturedItemSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->string(FeaturedItemSchema::FEATUREDABLE_TYPE);
            $table->unsignedBigInteger(FeaturedItemSchema::FEATUREDABLE_ID);
            $table->unsignedSmallInteger(FeaturedItemSchema::POSITION)->default(0);
            $table->timestamps();

            $table->unique(
                [TenantColumn::TENANT_ID, FeaturedItemSchema::FEATUREDABLE_TYPE, FeaturedItemSchema::FEATUREDABLE_ID],
                'catalog_featured_items_tenant_featuredable_unique'
            );
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
