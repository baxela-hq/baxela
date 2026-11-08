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
        // `name` is a DB-level enum — the new pricing-mode setting needs
        // its value in the column's allowed set (gap-C1b D1).
        Schema::table(SettingSchema::TABLE, function (Blueprint $table): void {
            $table->enum(SettingSchema::NAME, SettingNameEnum::cases())->change();
        });
    }

    public function down(): void
    {
        Schema::table(SettingSchema::TABLE, function (Blueprint $table): void {
            $without = array_filter(
                SettingNameEnum::cases(),
                fn (SettingNameEnum $case) => $case !== SettingNameEnum::PRICES_INCLUDE_TAX
            );
            $table->enum(SettingSchema::NAME, $without)->change();
        });
    }
};
