<?php

namespace Modules\Setting\Actions\Public\Setting;

use Illuminate\Database\Eloquent\Collection;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;

class ListSettingAction
{
    public function __construct(protected Setting $model) {}

    public function handle(): Collection
    {
        $publicNames = [
            SettingNameEnum::WEBSITE_TITLE,
            SettingNameEnum::WEBSITE_DESCRIPTION,
        ];

        return $this->model
            ->with(SettingSchema::RES_TRANSLATIONS)
            ->whereIn(SettingSchema::NAME, $publicNames)
            ->get();
    }
}
