<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Utils\OrderCodeGenerator;

return new class extends Migration
{
    /**
     * Add the opaque customer-facing order code to orders for databases
     * created before the column existed (fresh ones get it straight from the
     * create migration), then backfill existing rows.
     */
    public function up(): void
    {
        if (! Schema::hasColumn(OrderSchema::TABLE, OrderSchema::ORDER_CODE)) {
            Schema::table(OrderSchema::TABLE, function (Blueprint $table): void {
                $table->string(OrderSchema::ORDER_CODE)->nullable();
            });
        }

        $generator = app(OrderCodeGenerator::class);

        DB::table(OrderSchema::TABLE)
            ->whereNull(OrderSchema::ORDER_CODE)
            ->orderBy(OrderSchema::ID)
            ->get([OrderSchema::ID])
            ->each(function ($order) use ($generator): void {
                DB::table(OrderSchema::TABLE)
                    ->where(OrderSchema::ID, $order->{OrderSchema::ID})
                    ->update([OrderSchema::ORDER_CODE => $generator->generate()]);
            });

        $indexes = collect(Schema::getIndexes(OrderSchema::TABLE))->pluck('name');

        if (! $indexes->contains(fn (string $name): bool => str_ends_with($name, OrderSchema::ORDER_CODE.'_unique'))) {
            Schema::table(OrderSchema::TABLE, function (Blueprint $table): void {
                $table->unique(OrderSchema::ORDER_CODE);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(OrderSchema::TABLE, OrderSchema::ORDER_CODE)) {
            Schema::table(OrderSchema::TABLE, function (Blueprint $table): void {
                $table->dropColumn(OrderSchema::ORDER_CODE);
            });
        }
    }
};
