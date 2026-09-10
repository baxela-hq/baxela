<?php

namespace Modules\Menu\Transformers\Public\MenuLink;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Support\ResolvesPublicLanguage;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkTranslationSchema as MLTSchema;

class MenuLinkResource extends JsonResource
{
    use ResolvesPublicLanguage;

    /**
     * Transform the resource into an array. The title localizes by
     * Accept-Language and children nest recursively (already eager-loaded
     * and ordered).
     */
    public function toArray(Request $request): array
    {
        $translation = $this->resource->translations
            ->firstWhere(MLTSchema::LANGUAGE_ID, $this->resolvePublicLanguageId($request))
            ?? $this->resource->translations->first();

        return [
            MenuLinkSchema::ID => $this->resource->{MenuLinkSchema::ID},
            MenuLinkSchema::PARENT_ID => $this->resource->{MenuLinkSchema::PARENT_ID},
            MenuLinkSchema::POSITION => $this->resource->{MenuLinkSchema::POSITION},
            MenuLinkSchema::URL => $this->resource->{MenuLinkSchema::URL},
            MenuLinkSchema::TARGET => $this->resource->{MenuLinkSchema::TARGET},
            MLTSchema::TITLE => $translation?->{MLTSchema::TITLE},
            MenuLinkSchema::RES_CHILDREN => MenuLinkResource::collection($this->resource->children),
        ];
    }
}
