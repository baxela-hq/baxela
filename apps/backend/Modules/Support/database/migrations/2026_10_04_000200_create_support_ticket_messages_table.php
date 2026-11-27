<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Support\Schemas\Ticket\TicketSchema;
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
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->unsignedBigInteger(TicketMessageSchema::TICKET_ID)->index();
            $table->foreign([TenantColumn::TENANT_ID, TicketMessageSchema::TICKET_ID], 'support_ticket_messages_tenant_ticket_fk')
                ->references([TenantColumn::TENANT_ID, TicketSchema::ID])
                ->on(TicketSchema::TABLE)->cascadeOnDelete();
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
