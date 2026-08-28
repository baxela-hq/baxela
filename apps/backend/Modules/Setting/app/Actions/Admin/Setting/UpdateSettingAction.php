<?php

namespace Modules\Setting\Actions\Admin\Setting;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Setting\SettingUpdatedEvent;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingSchema;

class UpdateSettingAction
{
    public function __construct(protected Setting $model) {}

    public function handle(array $data): Collection
    {
        DB::beginTransaction();

        try {
            foreach ($data as $item) {
                $record = $this->model->where(SettingSchema::NAME, $item[SettingSchema::NAME])->firstOrFail();
                $record->update([SettingSchema::VALUE => $item[SettingSchema::VALUE]]);

                if (isset($item[SettingSchema::RES_TRANSLATIONS])) {
                    $record->translations()->delete();
                    foreach ($item[SettingSchema::RES_TRANSLATIONS] as $translation) {
                        $record->translations()->create($translation);
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            throw $e;
        }

        event(new SettingUpdatedEvent);

        return $this->model->with(SettingSchema::RES_TRANSLATIONS)->get();
    }
}
