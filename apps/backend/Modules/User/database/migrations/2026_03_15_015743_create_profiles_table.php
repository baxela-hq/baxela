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
            $table->string(ProfileSchema::FIRST_NAME);
            $table->string(ProfileSchema::LAST_NAME);
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
