<?php

namespace Modules\Catalog\Transformers\Admin\AttributeValue;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueTranslationSchema as Schema;
use Modules\Core\Transformers\ResolvesLanguageCodesTrait;

class AttributeValueTranslationResource extends JsonResource
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
            Schema::TITLE => $this->resource->{Schema::TITLE},
        ];
    }
}
