<?php

namespace Modules\Support\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Support\Database\Factories\TicketMessageFactory;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;
use Modules\Support\Schemas\TicketMessage\TicketSenderEnum;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property TicketSenderEnum $sender
 * @property string $body
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Ticket $ticket
 */
class TicketMessage extends Model
{
    /** @use HasFactory<TicketMessageFactory> */
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

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, TicketMessageSchema::TICKET_ID);
    }

    protected static function newFactory(): TicketMessageFactory
    {
        return TicketMessageFactory::new();
    }
}
