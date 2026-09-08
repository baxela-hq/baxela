<?php

namespace Modules\Menu\Actions\Admin\MenuLink;

use Illuminate\Database\Eloquent\Model;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;

class ShowMenuLinkAction extends AbstractMenuLinkAction
{
    public function handle(string $menuId, string $linkId): Model
    {
        return $this->model->query()
            ->where(MenuLinkSchema::MENU_ID, $menuId)
            ->with(MenuLinkSchema::RES_TRANSLATIONS)
            ->findOrFail($linkId);
    }
}
