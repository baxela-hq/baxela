<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
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
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            // Composite parent key for the layer-3 tenant-safe message FK.
            $table->unique([TenantColumn::TENANT_ID, TicketSchema::ID], 'support_tickets_tenant_id_unique');
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
