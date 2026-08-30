<?php

namespace Modules\Shipping\Transformers\Admin\Method;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Transformers\ResolvesLanguageCodesTrait;
use Modules\Shipping\Schemas\Method\MethodTranslationSchema as Schema;

class MethodTranslationResource extends JsonResource
{
    use ResolvesLanguageCodesTrait;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            Schema::LANGUAGE_ID => $this->resource->{Schema::LANGUAGE_ID},
            Schema::COL_LANGUAGE => $this->languageCode($this->resource->{Schema::LANGUAGE_ID}),
            Schema::NAME => $this->resource->{Schema::NAME},
            Schema::DESCRIPTION => $this->resource->{Schema::DESCRIPTION},
        ];
    }
}
