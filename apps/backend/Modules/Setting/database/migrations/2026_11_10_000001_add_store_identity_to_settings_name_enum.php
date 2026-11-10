<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;

return new class extends Migration
{
    public function up(): void
    {
        // `name` is a DB-level enum — the store legal identity settings
        // (gap-B1 Phase 2) need their values in the allowed set.
        Schema::table(SettingSchema::TABLE, function (Blueprint $table) {
            $table->enum(SettingSchema::NAME, SettingNameEnum::cases())->change();
        });
    }

    public function down(): void
    {
        Schema::table(SettingSchema::TABLE, function (Blueprint $table) {
            $identity = [
                SettingNameEnum::STORE_LEGAL_NAME,
                SettingNameEnum::STORE_LEGAL_ADDRESS,
                SettingNameEnum::STORE_TAX_ID,
                SettingNameEnum::STORE_CONTACT,
            ];
            $withoutIdentity = array_filter(
                SettingNameEnum::cases(),
                fn (SettingNameEnum $case) => ! in_array($case, $identity, true),
            );
            $table->enum(SettingSchema::NAME, $withoutIdentity)->change();
        });
    }
};
