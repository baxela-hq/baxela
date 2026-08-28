<?php

namespace Modules\Catalog\Transformers\Admin\Option;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Option\OptionTranslationSchema as Schema;
use Modules\Core\Transformers\ResolvesLanguageCodesTrait;

class OptionTranslationResource extends JsonResource
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
            Schema::SLUG => $this->resource->{Schema::SLUG},
        ];
    }
}
