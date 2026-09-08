<?php

namespace Modules\Menu\Transformers\Admin\Menu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Menu\Schemas\Menu\MenuSchema;
use Modules\Menu\Transformers\Admin\MenuLink\MenuLinkResource;

class MenuResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            MenuSchema::ID => $this->{MenuSchema::ID},
            MenuSchema::LOCATION => $this->{MenuSchema::LOCATION},
            MenuSchema::IS_ACTIVE => $this->{MenuSchema::IS_ACTIVE},
            MenuSchema::CREATED_AT => $this->{MenuSchema::CREATED_AT},
            MenuSchema::UPDATED_AT => $this->{MenuSchema::UPDATED_AT},
            MenuSchema::RES_TRANSLATIONS => MenuTranslationResource::collection($this->whenLoaded(MenuSchema::RES_TRANSLATIONS)),
            MenuSchema::RES_LINKS => MenuLinkResource::collection($this->whenLoaded(MenuSchema::RES_LINKS)),
        ];
    }
}
