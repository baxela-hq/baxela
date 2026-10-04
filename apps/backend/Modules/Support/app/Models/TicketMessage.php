<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Support\Database\Factories\TicketMessageFactory;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;
use Modules\Support\Schemas\TicketMessage\TicketSenderEnum;

class TicketMessage extends Model
{
    use HasFactory;

    protected $table = TicketMessageSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        TicketMessageSchema::TICKET_ID,
        TicketMessageSchema::USER_ID,
        TicketMessageSchema::SENDER,
        TicketMessageSchema::BODY,
    ];

    protected function casts(): array
    {
        return [
            TicketMessageSchema::SENDER => TicketSenderEnum::class,
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, TicketMessageSchema::TICKET_ID);
    }

    protected static function newFactory(): TicketMessageFactory
    {
        return TicketMessageFactory::new();
    }
}
