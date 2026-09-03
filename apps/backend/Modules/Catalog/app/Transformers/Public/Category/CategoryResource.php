<?php

namespace Modules\Catalog\Transformers\Public\Category;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\Category\CategoryTranslationSchema as CTSchema;
use Modules\Catalog\Support\ResolvesPublicLanguage;

class CategoryResource extends JsonResource
{
    use ResolvesPublicLanguage;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $translation = $this->resource->translations
            ->firstWhere(CTSchema::LANGUAGE_ID, $this->resolvePublicLanguageId($request))
            ?? $this->resource->translations->first();

        return [
            'id' => $this->resource->{CategorySchema::ID},
            'parent_id' => $this->resource->{CategorySchema::PARENT_ID},
            'position' => $this->resource->{CategorySchema::POSITION},
            'title' => $translation?->{CTSchema::TITLE},
            'slug' => $translation?->{CTSchema::SLUG},
        ];
    }
}
