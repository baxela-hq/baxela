<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Setting\Schemas\Setting\SettingGroupEnum;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Schemas\Setting\SettingTypeEnum;
use Modules\Setting\Schemas\Translation\TranslationSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(SettingSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID)->nullable();
            $table->index(TenantColumn::TENANT_ID);
            $table->enum(SettingSchema::GROUP, SettingGroupEnum::cases())->nullable();
            $table->enum(SettingSchema::TYPE, SettingTypeEnum::cases())->nullable();
            $table->enum(SettingSchema::NAME, SettingNameEnum::cases());
            $table->unique([TenantColumn::TENANT_ID, SettingSchema::NAME]);
            $table->text(SettingSchema::VALUE)->nullable();
            $table->boolean(SettingSchema::IS_TRANSLATABLE)->default(false);
            $table->string(SettingSchema::COMMENT)->nullable();
            $table->timestamps();
        });

        Schema::create(TranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->foreignId(TranslationSchema::SETTING_ID)
                ->constrained(SettingSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(TranslationSchema::LANGUAGE_ID);
            $table->text(TranslationSchema::VALUE)->nullable();
            $table->timestamps();
            $table->unique([TranslationSchema::SETTING_ID, TranslationSchema::LANGUAGE_ID]);
        });

        DB::statement(
            'CREATE UNIQUE INDEX setting_settings_name_platform_unique ON '.SettingSchema::TABLE
            .' ('.SettingSchema::NAME.') WHERE '.TenantColumn::TENANT_ID.' IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(TranslationSchema::TABLE);
        Schema::dropIfExists(SettingSchema::TABLE);
    }
};
