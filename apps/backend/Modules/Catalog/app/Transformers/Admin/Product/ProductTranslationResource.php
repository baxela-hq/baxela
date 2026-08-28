<?php

namespace Modules\Catalog\Transformers\Admin\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as Schema;
use Modules\Core\Transformers\ResolvesLanguageCodesTrait;

class ProductTranslationResource extends JsonResource
{
    use ResolvesLanguageCodesTrait;

    public function toArray(Request $request): array
    {
        return [
            Schema::ID => $this->resource->{Schema::ID},
            Schema::LANGUAGE_ID => $this->resource->{Schema::LANGUAGE_ID},
            Schema::COL_LANGUAGE => $this->languageCode($this->resource->{Schema::LANGUAGE_ID}),
            Schema::TITLE => $this->resource->{Schema::TITLE},
            Schema::SLUG => $this->resource->{Schema::SLUG},
            Schema::CONTENT => $this->resource->{Schema::CONTENT},
            Schema::DESCRIPTION => $this->resource->{Schema::DESCRIPTION},
            Schema::CREATED_AT => $this->resource->{Schema::CREATED_AT},
            Schema::UPDATED_AT => $this->resource->{Schema::UPDATED_AT},
        ];
    }
}
