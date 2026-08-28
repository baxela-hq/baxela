<?php

namespace Modules\Setting\Schemas\Setting;

enum SettingNameEnum: string
{
    case WEBSITE_TITLE = 'website_title';
    case WEBSITE_DESCRIPTION = 'website_description';
    case LANGUAGE_ID = 'language_id';

    case CURRENCY_ID = 'currency_id';
}
