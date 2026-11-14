<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Auth\Schemas\SocialAccount\SocialAccountSchema;
use Modules\Auth\Schemas\User\UserSchema;

return new class extends Migration
{
    /**
     * Google sign-in identity links (customers only). The provider pair
     * `(provider, provider_user_id)` is the canonical identity — Google's
     * `sub` is permanent while emails can move — and its unique constraint
     * arbitrates concurrent first sign-ins. The `(user_id, provider)`
     * unique enforces one identity per provider per account. Both
     * constraints double as the lookup indexes.
     */
    public function up(): void
    {
        Schema::create(SocialAccountSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(SocialAccountSchema::USER_ID)
                ->constrained(UserSchema::TABLE)
                ->cascadeOnDelete();
            $table->string(SocialAccountSchema::PROVIDER, 32);
            $table->string(SocialAccountSchema::PROVIDER_USER_ID, 191);
            $table->timestamps();

            $table->unique([SocialAccountSchema::PROVIDER, SocialAccountSchema::PROVIDER_USER_ID]);
            $table->unique([SocialAccountSchema::USER_ID, SocialAccountSchema::PROVIDER]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(SocialAccountSchema::TABLE);
    }
};
