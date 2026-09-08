<?php

namespace Modules\Menu\Transformers\Admin\MenuLink;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;

class MenuLinkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            MenuLinkSchema::ID => $this->{MenuLinkSchema::ID},
            MenuLinkSchema::MENU_ID => $this->{MenuLinkSchema::MENU_ID},
            MenuLinkSchema::PARENT_ID => $this->{MenuLinkSchema::PARENT_ID},
            MenuLinkSchema::POSITION => $this->{MenuLinkSchema::POSITION},
            MenuLinkSchema::URL => $this->{MenuLinkSchema::URL},
            MenuLinkSchema::TARGET => $this->{MenuLinkSchema::TARGET},
            MenuLinkSchema::CREATED_AT => $this->{MenuLinkSchema::CREATED_AT},
            MenuLinkSchema::UPDATED_AT => $this->{MenuLinkSchema::UPDATED_AT},
            MenuLinkSchema::RES_TRANSLATIONS => MenuLinkTranslationResource::collection($this->whenLoaded(MenuLinkSchema::RES_TRANSLATIONS)),
        ];
    }
}
