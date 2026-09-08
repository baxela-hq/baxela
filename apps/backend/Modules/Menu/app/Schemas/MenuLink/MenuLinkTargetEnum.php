<?php

namespace Modules\Menu\Schemas\MenuLink;

enum MenuLinkTargetEnum: string
{
    case SELF = '_self';

    case BLANK = '_blank';
}
