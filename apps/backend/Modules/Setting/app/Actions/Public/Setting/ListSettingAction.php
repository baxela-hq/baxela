<?php

namespace Modules\Setting\Actions\Public\Setting;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;

class ListSettingAction
{
    public function __construct(protected Setting $model) {}

    /** @return Collection<int, Setting> */
    public function handle(Request $request): Collection
    {
        $publicNames = [
            SettingNameEnum::WEBSITE_TITLE,
            SettingNameEnum::WEBSITE_DESCRIPTION,
            SettingNameEnum::ANNOUNCEMENT_TEXT,
            SettingNameEnum::ANNOUNCEMENT_BAR_ENABLED,
            SettingNameEnum::STOREFRONT_THEME,
            SettingNameEnum::TIMEZONE,
            SettingNameEnum::PRICES_INCLUDE_TAX,
        ];

        return $this->model
            ->with(SettingSchema::RES_TRANSLATIONS)
            ->whereIn(SettingSchema::NAME, $publicNames)
            ->get();
    }
}
