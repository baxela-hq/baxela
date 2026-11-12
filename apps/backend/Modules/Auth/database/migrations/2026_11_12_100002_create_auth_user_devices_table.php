<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Schemas\UserDevice\UserDeviceSchema;

return new class extends Migration
{
    /**
     * S.4 persistent device memory — survives sign-out, forced revocation
     * and token expiry, so "new device" notifications cannot cry wolf on a
     * device the user has used all along. The unique constraint is the
     * arbiter for concurrent first registration and doubles as the lookup
     * index — no separate duplicate index (F9).
     */
    public function up(): void
    {
        Schema::create(UserDeviceSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(UserDeviceSchema::USER_ID)
                ->constrained(UserSchema::TABLE)
                ->cascadeOnDelete();
            $table->string(UserDeviceSchema::DEVICE_NAME, 100);
            $table->timestamp(UserDeviceSchema::FIRST_SEEN_AT);
            $table->timestamp(UserDeviceSchema::LAST_SEEN_AT);
            $table->timestamps();

            $table->unique([UserDeviceSchema::USER_ID, UserDeviceSchema::DEVICE_NAME]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(UserDeviceSchema::TABLE);
    }
};
