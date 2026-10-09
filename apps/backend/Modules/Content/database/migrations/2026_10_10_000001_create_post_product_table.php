<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Content\Schemas\Post\PostProductSchema;
use Modules\Content\Schemas\Post\PostSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(PostProductSchema::TABLE, function (Blueprint $table) {
            $table->foreignId(PostProductSchema::POST_ID)
                ->constrained(PostSchema::TABLE)->onDelete('cascade');
            // Plain int on purpose: products belong to the Catalog module
            // and cross-module tables never get foreign keys here; ids are
            // validated through the Catalog gateway instead.
            $table->unsignedBigInteger(PostProductSchema::PRODUCT_ID);

            $table->primary([PostProductSchema::POST_ID, PostProductSchema::PRODUCT_ID]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(PostProductSchema::TABLE);
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
};
