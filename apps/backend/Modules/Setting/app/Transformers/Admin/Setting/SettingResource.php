<?php

namespace Modules\Setting\Transformers\Admin\Setting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Setting\Schemas\Setting\SettingSchema;

class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            SettingSchema::GROUP => $this->resource->{SettingSchema::GROUP},
            SettingSchema::TYPE => $this->resource->{SettingSchema::TYPE},
            SettingSchema::NAME => $this->resource->{SettingSchema::NAME},
            SettingSchema::VALUE => $this->resource->{SettingSchema::VALUE},
            SettingSchema::IS_TRANSLATABLE => $this->resource->{SettingSchema::IS_TRANSLATABLE},
            SettingSchema::COMMENT => $this->resource->{SettingSchema::COMMENT},
            SettingSchema::RES_TRANSLATIONS => TranslationResource::collection($this->whenLoaded(SettingSchema::RES_TRANSLATIONS)),
        ];
    }
}
