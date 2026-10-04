<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Support\Database\Factories\TicketFactory;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Schemas\Ticket\TicketStatusEnum;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;

class Ticket extends Model
{
    use HasFactory;

    protected $table = TicketSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        TicketSchema::USER_ID,
        TicketSchema::ORDER_CODE,
        TicketSchema::SUBJECT,
        TicketSchema::STATUS,
        TicketSchema::LAST_MESSAGE_AT,
    ];

    protected function casts(): array
    {
        return [
            TicketSchema::STATUS => TicketStatusEnum::class,
            TicketSchema::LAST_MESSAGE_AT => 'datetime',
        ];
    }

    /**
     * Conversation messages, oldest first.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, TicketMessageSchema::TICKET_ID)
            ->orderBy(TicketMessageSchema::CREATED_AT);
    }

    protected static function newFactory(): TicketFactory
    {
        return TicketFactory::new();
    }
}
