<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Schemas\User\UserSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(UserSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(UserSchema::EMAIL)->unique();
            $table->timestamp(UserSchema::EMAIL_VERIFIED_AT)->nullable();
            //            $table->string(UserSchema::MOBILE)->nullable()->unique();
            //            $table->timestamp(UserSchema::MOBILE_VERIFIED_AT)->nullable();
            $table->string(UserSchema::PASSWORD);
            $table->boolean(UserSchema::IS_ACTIVE)->index()->default(false);
            $table->boolean(UserSchema::IS_ADMIN)->default(false);
            $table->string(UserSchema::COMMENT)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(UserSchema::TABLE);
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
