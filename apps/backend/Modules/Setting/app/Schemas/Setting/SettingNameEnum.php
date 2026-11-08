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

    case STOREFRONT_THEME = 'storefront_theme';

    case TIMEZONE = 'timezone';

    /**
     * Store-level pricing mode (gap-C1b D1): whether displayed product
     * prices contain tax. The merchant's pricing policy — jurisdiction
     * semantics live on the tax rates, this is the price-tag contract.
     */
    case PRICES_INCLUDE_TAX = 'prices_include_tax';
}
