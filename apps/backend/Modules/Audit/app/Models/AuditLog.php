<?php

namespace Modules\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;
use Modules\Auth\Models\User;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, AuditLogSchema::USER_ID);
    }
}
