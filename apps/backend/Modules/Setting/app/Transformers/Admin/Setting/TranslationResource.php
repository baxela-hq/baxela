<?php

namespace Modules\Setting\Transformers\Admin\Setting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Setting\Schemas\Translation\TranslationSchema;

class TranslationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            TranslationSchema::ID => $this->resource->id,
            TranslationSchema::LANGUAGE_ID => $this->resource->{TranslationSchema::LANGUAGE_ID},
            TranslationSchema::VALUE => $this->resource->{TranslationSchema::VALUE},
        ];
    }
}
