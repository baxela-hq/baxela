<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Schemas\TransferRemap\TransferRemapSchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TransferRemapSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
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
            // The leading tenant_id is the destination store (§4.5 class T)
            // — without it, one store's import of a shared artifact would
            // block every other store's import of the same source key.
            // The index name is explicit: the auto-generated one exceeds
            // MySQL's 64-character identifier limit.
            $table->unique([
                TenantColumn::TENANT_ID,
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
