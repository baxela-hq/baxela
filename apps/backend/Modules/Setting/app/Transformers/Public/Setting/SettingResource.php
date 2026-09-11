<?php

namespace Modules\Setting\Transformers\Public\Setting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Support\ResolvesPublicLanguage;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Schemas\Translation\TranslationSchema;

class SettingResource extends JsonResource
{
    use ResolvesPublicLanguage;

    public function toArray(Request $request): array
    {
        $translation = $this->resource->translations
            ->firstWhere(TranslationSchema::LANGUAGE_ID, $this->resolvePublicLanguageId($request));

        return [
            SettingSchema::GROUP => $this->resource->{SettingSchema::GROUP},
            SettingSchema::TYPE => $this->resource->{SettingSchema::TYPE},
            SettingSchema::NAME => $this->resource->{SettingSchema::NAME},
            SettingSchema::VALUE => $translation?->{TranslationSchema::VALUE} ?? $this->resource->{SettingSchema::VALUE},
            SettingSchema::RES_TRANSLATIONS => TranslationResource::collection($this->whenLoaded(SettingSchema::RES_TRANSLATIONS)),
        ];
    }
}
