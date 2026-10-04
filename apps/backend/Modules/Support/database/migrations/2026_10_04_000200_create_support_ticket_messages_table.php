<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;
use Modules\Support\Schemas\TicketMessage\TicketSenderEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(TicketMessageSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TicketMessageSchema::TICKET_ID)->index();
            $table->unsignedBigInteger(TicketMessageSchema::USER_ID)->index();
            $table->enum(TicketMessageSchema::SENDER, TicketSenderEnum::cases())
                ->default(TicketSenderEnum::CUSTOMER->value);
            $table->text(TicketMessageSchema::BODY);
            $table->timestamps();

            $table->index([TicketMessageSchema::TICKET_ID, TicketMessageSchema::CREATED_AT]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(TicketMessageSchema::TABLE);
    }
};
