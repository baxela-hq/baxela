<?php

namespace Modules\Content\Transformers\Public\Page;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Schemas\Page\PageSchema;
use Modules\Content\Schemas\Page\PageTranslationSchema as PTSchema;
use Modules\Core\Support\ResolvesPublicLanguage;

class PageResource extends JsonResource
{
    use ResolvesPublicLanguage;

    /**
     * Transform the resource into an array. The page is served in the
     * visitor's language (Accept-Language), falling back to the default.
     */
    public function toArray(Request $request): array
    {
        $translation = $this->resource->translations
            ->firstWhere(PTSchema::LANGUAGE_ID, $this->resolvePublicLanguageId($request))
            ?? $this->resource->translations->first();

        return [
            PageSchema::ID => $this->resource->{PageSchema::ID},
            PTSchema::TITLE => $translation?->{PTSchema::TITLE},
            PTSchema::SLUG => $translation?->{PTSchema::SLUG},
            PTSchema::DESCRIPTION => $translation?->{PTSchema::DESCRIPTION},
            PTSchema::CONTENT => $translation?->{PTSchema::CONTENT},
            PageSchema::CREATED_AT => $this->resource->{PageSchema::CREATED_AT},
            PageSchema::UPDATED_AT => $this->resource->{PageSchema::UPDATED_AT},
        ];
    }
}
