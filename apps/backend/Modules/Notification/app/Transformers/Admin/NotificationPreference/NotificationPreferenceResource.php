<?php

namespace Modules\Notification\Transformers\Admin\NotificationPreference;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notification\Schemas\Notification\NotificationPreferenceSchema;

class NotificationPreferenceResource extends JsonResource
{
    /**
     * One configurable notification merged with the caller's overrides:
     * code, locked flag, and per-channel enabled/default state. Wraps the
     * arrays built by PreferenceMatrix.
     *
     * @param  array<string, mixed>  $resource
     */
    public function toArray(Request $request): array
    {
        return [
            NotificationPreferenceSchema::CODE => $this->resource[NotificationPreferenceSchema::CODE],
            NotificationPreferenceSchema::RES_LOCKED => $this->resource[NotificationPreferenceSchema::RES_LOCKED],
            NotificationPreferenceSchema::RES_CHANNELS => $this->resource[NotificationPreferenceSchema::RES_CHANNELS],
        ];
    }
}
