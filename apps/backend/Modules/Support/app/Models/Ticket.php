<?php

namespace Modules\Support\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Support\Database\Factories\TicketFactory;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Schemas\Ticket\TicketStatusEnum;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $order_code
 * @property string $subject
 * @property TicketStatusEnum $status
 * @property CarbonInterface|null $last_message_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, TicketMessage> $messages
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    use TenantScopedTrait;

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
    /** @return HasMany<TicketMessage, $this> */
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
