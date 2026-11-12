<?php

namespace Modules\Setting\Transformers\Admin\Setting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;

class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            SettingSchema::GROUP => $this->resource->{SettingSchema::GROUP},
            SettingSchema::TYPE => $this->resource->{SettingSchema::TYPE},
            SettingSchema::NAME => $this->resource->{SettingSchema::NAME},
            // The FX credential is write-only (review F8): masked on the
            // list AND on the update response (this resource serves both).
            // The UI shows the mask as a configured-state indicator and
            // submits empty / null — never this placeholder.
            SettingSchema::VALUE => $this->maskedValue(),
            SettingSchema::IS_TRANSLATABLE => $this->resource->{SettingSchema::IS_TRANSLATABLE},
            SettingSchema::COMMENT => $this->resource->{SettingSchema::COMMENT},
            SettingSchema::RES_TRANSLATIONS => TranslationResource::collection($this->whenLoaded(SettingSchema::RES_TRANSLATIONS)),
        ];
    }

    protected function maskedValue(): mixed
    {
        $value = $this->resource->{SettingSchema::VALUE};

        // `name` is enum-cast on the model.
        $name = $this->resource->{SettingSchema::NAME};
        $name = $name instanceof SettingNameEnum ? $name->value : $name;

        if ($name === SettingNameEnum::FX_API_KEY->value
            && is_string($value)
            && $value !== '') {
            return Setting::MASKED_VALUE;
        }

        return $value;
    }
}
