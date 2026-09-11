<?php

namespace Modules\Setting\Schemas\Setting;

enum SettingNameEnum: string
{
    case WEBSITE_TITLE = 'website_title';
    case WEBSITE_DESCRIPTION = 'website_description';
    case LANGUAGE_ID = 'language_id';

    case CURRENCY_ID = 'currency_id';

    case ANNOUNCEMENT_TEXT = 'announcement_text';
    case ANNOUNCEMENT_BAR_ENABLED = 'announcement_bar_enabled';
}
