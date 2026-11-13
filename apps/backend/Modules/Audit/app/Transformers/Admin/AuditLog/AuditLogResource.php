<?php

namespace Modules\Audit\Transformers\Admin\AuditLog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;

/**
 * @property int $id
 * @property string $module
 * @property string $entity
 * @property string $action
 * @property int|string|null $entity_id
 * @property array<string, mixed>|null $properties
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property User|null $user
 */
class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            AuditLogSchema::ID => $this->{AuditLogSchema::ID},
            AuditLogSchema::MODULE => $this->{AuditLogSchema::MODULE},
            AuditLogSchema::ENTITY => $this->{AuditLogSchema::ENTITY},
            AuditLogSchema::ACTION => $this->{AuditLogSchema::ACTION},
            AuditLogSchema::ENTITY_ID => $this->{AuditLogSchema::ENTITY_ID},
            AuditLogSchema::PROPERTIES => $this->{AuditLogSchema::PROPERTIES},
            AuditLogSchema::IP_ADDRESS => $this->{AuditLogSchema::IP_ADDRESS},
            AuditLogSchema::USER_AGENT => $this->{AuditLogSchema::USER_AGENT},
            AuditLogSchema::CREATED_AT => $this->{AuditLogSchema::CREATED_AT},
            AuditLogSchema::RES_USER => $this->whenLoaded(AuditLogSchema::RES_USER, fn () => [
                UserSchema::ID => $this->user?->{UserSchema::ID},
                UserSchema::EMAIL => $this->user?->{UserSchema::EMAIL},
            ]),
        ];
    }
}
