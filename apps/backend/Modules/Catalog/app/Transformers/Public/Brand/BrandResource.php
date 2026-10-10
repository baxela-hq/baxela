<?php

namespace Modules\Catalog\Transformers\Public\Brand;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Brand\BrandSchema;
use Modules\Catalog\Schemas\Brand\BrandTranslationSchema as BTSchema;
use Modules\Catalog\Support\ResolvesPublicLanguage;

class BrandResource extends JsonResource
{
    use ResolvesPublicLanguage;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $translation = $this->resource->translations
            ->firstWhere(BTSchema::LANGUAGE_ID, $this->resolvePublicLanguageId($request))
            ?? $this->resource->translations->first();

        return [
            'id' => $this->resource->{BrandSchema::ID},
            'position' => $this->resource->{BrandSchema::POSITION},
            'image_url' => $this->resource->{BrandSchema::IMAGE_URL},
            'title' => $translation?->{BTSchema::TITLE},
            'slug' => $translation?->{BTSchema::SLUG},
            'description' => $translation?->{BTSchema::DESCRIPTION},
        ];
    }
}
