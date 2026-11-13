<?php

namespace Modules\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;
use Modules\Auth\Models\User;

/**
 * One immutable audit row for an admin mutation (S.2). Column names are
 * owned by AuditLogSchema and consumed dynamically across the module.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $module
 * @property string $entity
 * @property string $action
 * @property int|string|null $entity_id
 * @property array<string, mixed>|null $properties
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property User|null $user
 */
class AuditLog extends Model
{
    protected $table = AuditLogSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        AuditLogSchema::USER_ID,
        AuditLogSchema::MODULE,
        AuditLogSchema::ENTITY,
        AuditLogSchema::ACTION,
        AuditLogSchema::ENTITY_ID,
        AuditLogSchema::PROPERTIES,
        AuditLogSchema::IP_ADDRESS,
        AuditLogSchema::USER_AGENT,
    ];

    protected function casts(): array
    {
        return [
            AuditLogSchema::PROPERTIES => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, AuditLogSchema::USER_ID);
    }
}
