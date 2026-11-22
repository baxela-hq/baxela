<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\TransferLock\TransferLockSchema;

return new class extends Migration
{
    /**
     * Destination-module import locks (postgres-migration §3.1): one row
     * per lock name, carrying the owning run's token, the fencing
     * generation (incremented only by successful reclamation), and a
     * DB-clock lease. Microsecond precision keeps expiry decisions exact
     * on MySQL 8 / PostgreSQL; SQLite stores text and compares
     * lexicographically on the identical strftime format.
     */
    public function up(): void
    {
        Schema::create(TransferLockSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(TransferLockSchema::NAME)->unique();
            $table->uuid(TransferLockSchema::OWNER_TOKEN);
            $table->unsignedBigInteger(TransferLockSchema::GENERATION);
            $table->timestamp(TransferLockSchema::HEARTBEAT_AT, 6)->nullable();
            $table->timestamp(TransferLockSchema::EXPIRES_AT, 6)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TransferLockSchema::TABLE);
    }
};
