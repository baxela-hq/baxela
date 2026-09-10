<?php

namespace Modules\Menu\Transformers\Public\Menu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Support\ResolvesPublicLanguage;
use Modules\Menu\Schemas\Menu\MenuSchema;
use Modules\Menu\Schemas\Menu\MenuTranslationSchema as MTSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Modules\Menu\Transformers\Public\MenuLink\MenuLinkResource;

class MenuResource extends JsonResource
{
    use ResolvesPublicLanguage;

    /**
     * Transform the resource into an array. Titles localize by
     * Accept-Language; only the top-level links are returned — each one
     * nests its own children.
     */
    public function toArray(Request $request): array
    {
        $translation = $this->resource->translations
            ->firstWhere(MTSchema::LANGUAGE_ID, $this->resolvePublicLanguageId($request))
            ?? $this->resource->translations->first();

        $links = $this->resource->links
            ->whereNull(MenuLinkSchema::PARENT_ID)
            ->values();

        return [
            MenuSchema::ID => $this->resource->{MenuSchema::ID},
            MenuSchema::LOCATION => $this->resource->{MenuSchema::LOCATION},
            MenuSchema::IS_ACTIVE => $this->resource->{MenuSchema::IS_ACTIVE},
            MTSchema::TITLE => $translation?->{MTSchema::TITLE},
            MenuSchema::RES_LINKS => MenuLinkResource::collection($links),
        ];
    }
}
