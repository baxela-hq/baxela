<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\User\Schemas\Profile\ProfileSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(ProfileSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(ProfileSchema::USER_ID)->unique();
            $table->string(ProfileSchema::FULL_NAME);
            $table->string(ProfileSchema::DISPLAY_NAME)->nullable();
            $table->text(ProfileSchema::BIO)->nullable();
            $table->string(ProfileSchema::AVATAR)->nullable();
            $table->string(ProfileSchema::GENDER)->nullable();
            $table->date(ProfileSchema::DATE_OF_BIRTH)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ProfileSchema::TABLE);
    }
};
