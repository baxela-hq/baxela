<?php

namespace Modules\Audit\Transformers\Admin\AuditLog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;
use Modules\Auth\Schemas\User\UserSchema;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
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
