<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Content\Schemas\Page\PageSchema;
use Modules\Content\Schemas\Page\PageStatusEnum;
use Modules\Content\Schemas\Page\PageTranslationSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(PageSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->enum(PageSchema::STATUS, PageStatusEnum::cases());
            $table->timestamps();
        });

        Schema::create(PageTranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(PageTranslationSchema::PAGE_ID)
                ->constrained(PageSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(PageTranslationSchema::LANGUAGE_ID)->nullable();
            $table->string(PageTranslationSchema::TITLE);
            $table->string(PageTranslationSchema::SLUG);
            $table->mediumText(PageTranslationSchema::CONTENT);
            $table->string(PageTranslationSchema::DESCRIPTION)->nullable();
            $table->timestamps();

            $table->unique([PageTranslationSchema::PAGE_ID, PageTranslationSchema::LANGUAGE_ID]);
            $table->unique([PageTranslationSchema::LANGUAGE_ID, PageTranslationSchema::SLUG]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(PageTranslationSchema::TABLE);
        Schema::dropIfExists(PageSchema::TABLE);
    }
};
