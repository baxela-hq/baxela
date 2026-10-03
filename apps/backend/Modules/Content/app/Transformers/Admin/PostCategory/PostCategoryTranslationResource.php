<?php

namespace Modules\Content\Transformers\Admin\PostCategory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema as Schema;
use Modules\Core\Transformers\ResolvesLanguageCodesTrait;

class PostCategoryTranslationResource extends JsonResource
{
    use ResolvesLanguageCodesTrait;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            Schema::ID => $this->resource->{Schema::ID},
            Schema::LANGUAGE_ID => $this->resource->{Schema::LANGUAGE_ID},
            Schema::COL_LANGUAGE => $this->languageCode($this->resource->{Schema::LANGUAGE_ID}),
            Schema::TITLE => $this->resource->{Schema::TITLE},
            Schema::SLUG => $this->resource->{Schema::SLUG},
            Schema::DESCRIPTION => $this->resource->{Schema::DESCRIPTION},
            Schema::CREATED_AT => $this->resource->{Schema::CREATED_AT},
            Schema::UPDATED_AT => $this->resource->{Schema::UPDATED_AT},
        ];
    }
}
