<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Contact\Schemas\ContactMessage\ContactMessageSchema;
use Modules\Contact\Schemas\ContactMessage\ContactMessageStatusEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(ContactMessageSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(ContactMessageSchema::NAME);
            $table->string(ContactMessageSchema::EMAIL);
            $table->string(ContactMessageSchema::PHONE)->nullable();
            $table->string(ContactMessageSchema::SUBJECT);
            $table->text(ContactMessageSchema::CONTENT);
            $table->enum(ContactMessageSchema::STATUS, ContactMessageStatusEnum::cases())
                ->default(ContactMessageStatusEnum::UNREAD->value);
            $table->string(ContactMessageSchema::IP_ADDRESS, 45)->nullable();
            $table->timestamps();

            $table->index([ContactMessageSchema::STATUS, ContactMessageSchema::CREATED_AT]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ContactMessageSchema::TABLE);
    }
};
