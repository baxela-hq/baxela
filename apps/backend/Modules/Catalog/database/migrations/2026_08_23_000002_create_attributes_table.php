<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\Attribute\AttributeTranslationSchema;
use Modules\Catalog\Schemas\Attribute\AttributeTypeEnum;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Modules\Core\Schemas\Shared\TenantColumn;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(AttributeSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->foreignId(AttributeSchema::GROUP_ID)
                ->constrained(AttributeGroupSchema::TABLE)->restrictOnDelete();
            $table->string(AttributeSchema::CODE);
            $table->unique([TenantColumn::TENANT_ID, AttributeSchema::CODE]);
            $table->enum(AttributeSchema::DATA_TYPE, AttributeTypeEnum::cases());
            $table->boolean(AttributeSchema::IS_FILTERABLE)->default(false);
            $table->unsignedSmallInteger(AttributeSchema::POSITION)->nullable();
            $table->timestamps();
        });

        Schema::create(AttributeTranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->foreignId(AttributeTranslationSchema::ATTRIBUTE_ID)
                ->constrained(AttributeSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(AttributeTranslationSchema::LANGUAGE_ID);
            $table->string(AttributeTranslationSchema::TITLE);
            $table->timestamps();

            $table->unique([AttributeTranslationSchema::ATTRIBUTE_ID,
                AttributeTranslationSchema::LANGUAGE_ID]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(AttributeTranslationSchema::TABLE);
        Schema::dropIfExists(AttributeSchema::TABLE);
    }
};
