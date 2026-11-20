<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\TransferRemap\TransferRemapSchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TransferRemapSchema::TABLE, function (Blueprint $table) {
            $table->id();
            // The source installation namespacing is what lets two
            // independent stores both carrying `user 42` import into one
            // destination without cross-contamination (C1).
            $table->string(TransferRemapSchema::SOURCE_INSTALLATION, 36);
            $table->string(TransferRemapSchema::MODULE)->index();
            $table->string(TransferRemapSchema::ENTITY);
            $table->unsignedBigInteger(TransferRemapSchema::SOURCE_ID);
            $table->unsignedBigInteger(TransferRemapSchema::LOCAL_ID)->index();
            // Idempotent re-import: recording the same mapping again
            // upserts, so repeated restores converge instead of piling up.
            // The index name is explicit: the auto-generated one exceeds
            // MySQL's 64-character identifier limit.
            $table->unique([
                TransferRemapSchema::SOURCE_INSTALLATION,
                TransferRemapSchema::MODULE,
                TransferRemapSchema::ENTITY,
                TransferRemapSchema::SOURCE_ID,
            ], 'transfer_remaps_source_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TransferRemapSchema::TABLE);
    }
};
