<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Setting\Schemas\Setting\SettingGroupEnum;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Schemas\Setting\SettingTypeEnum;

return new class extends Migration
{
    public function up(): void
    {
        // `name` is a DB-level enum — the new timezone setting needs its
        // value in the column's allowed set (MySQL truncates otherwise).
        Schema::table(SettingSchema::TABLE, function (Blueprint $table) {
            $table->enum(SettingSchema::NAME, SettingNameEnum::cases())->change();
        });
    }

    public function down(): void
    {
        Schema::table(SettingSchema::TABLE, function (Blueprint $table) {
            $withoutTimezone = array_filter(
                SettingNameEnum::cases(),
                fn (SettingNameEnum $case) => $case !== SettingNameEnum::TIMEZONE
            );
            $table->enum(SettingSchema::NAME, $withoutTimezone)->change();
        });
    }
};
