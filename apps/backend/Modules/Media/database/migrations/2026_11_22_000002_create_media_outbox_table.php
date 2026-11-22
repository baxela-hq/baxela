<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Media\Schemas\MediaOutbox\MediaOutboxSchema;

return new class extends Migration
{
    /**
     * Transactional media-publication outbox (postgres-migration §3.3):
     * one row per media creation, committed in the same transaction as
     * the media row. The processor's claim carries a token + monotonically
     * advancing epoch — the claim-conditioned terminal transition is the
     * only path to authoritative publication (a stale processor can
     * neither complete an entry nor re-point the media row).
     */
    public function up(): void
    {
        Schema::create(MediaOutboxSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(MediaOutboxSchema::MEDIA_ID)->index();
            $table->char(MediaOutboxSchema::EXPECTED_HASH, 64);
            $table->string(MediaOutboxSchema::STAGING_PATH);
            $table->string(MediaOutboxSchema::FINAL_KEY);
            $table->string(MediaOutboxSchema::STATUS)->default(MediaOutboxSchema::STATUS_PENDING)->index();
            $table->uuid(MediaOutboxSchema::CLAIM_TOKEN)->nullable();
            $table->unsignedBigInteger(MediaOutboxSchema::CLAIM_EPOCH)->default(0);
            $table->timestamp(MediaOutboxSchema::CLAIMED_AT)->nullable();
            $table->string(MediaOutboxSchema::PUBLISHED_PATH)->nullable();
            $table->timestamp(MediaOutboxSchema::PUBLISHED_AT)->nullable();
            $table->unsignedInteger(MediaOutboxSchema::ATTEMPTS)->default(0);
            $table->text(MediaOutboxSchema::LAST_ERROR)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(MediaOutboxSchema::TABLE);
    }
};
