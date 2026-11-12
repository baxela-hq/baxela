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
        // `name` is a DB-level enum — the FX auto-sync settings
        // (launch-readiness Part E) need their values in the column's
        // allowed set.
        Schema::table(SettingSchema::TABLE, function (Blueprint $table): void {
            $table->enum(SettingSchema::NAME, SettingNameEnum::cases())->change();
        });
    }

    public function down(): void
    {
        Schema::table(SettingSchema::TABLE, function (Blueprint $table): void {
            $without = array_filter(
                SettingNameEnum::cases(),
                fn (SettingNameEnum $case) => ! in_array($case, [
                    SettingNameEnum::FX_AUTO_SYNC_ENABLED,
                    SettingNameEnum::FX_PROVIDER,
                    SettingNameEnum::FX_API_KEY,
                    SettingNameEnum::FX_SYNC_INTERVAL_HOURS,
                    SettingNameEnum::FX_SYNC_STATUS,
                ], true)
            );

            $table->enum(SettingSchema::NAME, $without)->change();
        });
    }
};
