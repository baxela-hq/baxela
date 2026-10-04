<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Schemas\Ticket\TicketStatusEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(TicketSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TicketSchema::USER_ID)->index();
            $table->string(TicketSchema::ORDER_CODE, 64)->nullable();
            $table->string(TicketSchema::SUBJECT);
            $table->enum(TicketSchema::STATUS, TicketStatusEnum::cases())
                ->default(TicketStatusEnum::OPEN->value);
            $table->timestamp(TicketSchema::LAST_MESSAGE_AT)->nullable();
            $table->timestamps();

            $table->index([TicketSchema::STATUS, TicketSchema::CREATED_AT]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(TicketSchema::TABLE);
    }
};
