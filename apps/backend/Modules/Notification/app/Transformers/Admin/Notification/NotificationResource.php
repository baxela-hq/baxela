<?php

namespace Modules\Notification\Transformers\Admin\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notification\Schemas\Notification\NotificationSchema;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            NotificationSchema::ID => $this->resource->{NotificationSchema::ID},
            NotificationSchema::CODE => $this->resource->{NotificationSchema::CODE},
            NotificationSchema::TITLE => $this->resource->{NotificationSchema::TITLE},
            NotificationSchema::BODY => $this->resource->{NotificationSchema::BODY},
            NotificationSchema::META => $this->resource->{NotificationSchema::META},
            NotificationSchema::READ_AT => $this->resource->{NotificationSchema::READ_AT},
            NotificationSchema::CREATED_AT => $this->resource->{NotificationSchema::CREATED_AT},
        ];
    }
}
