<?php

namespace Modules\Setting\Actions\Admin\Setting;

use Illuminate\Database\Eloquent\Collection;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingSchema;

class ListSettingAction
{
    public function __construct(protected Setting $model) {}

    public function handle(): Collection
    {
        return $this->model->with(SettingSchema::RES_TRANSLATIONS)->get();
    }
}
