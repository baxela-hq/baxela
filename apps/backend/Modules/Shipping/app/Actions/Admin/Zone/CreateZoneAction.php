<?php

namespace Modules\Shipping\Actions\Admin\Zone;

use Illuminate\Support\Facades\DB;
use Modules\Shipping\Exceptions\Zone\CreationFailedException;
use Modules\Shipping\Models\Zone;
use Modules\Shipping\Schemas\Zone\ZoneSchema;
use Throwable;

class CreateZoneAction
{
    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(array $data): Zone
    {
        try {
            DB::beginTransaction();

            $record = Zone::query()->create([
                ZoneSchema::NAME => $data[ZoneSchema::NAME],
                ZoneSchema::IS_ACTIVE => $data[ZoneSchema::IS_ACTIVE],
                ZoneSchema::POSITION => $data[ZoneSchema::POSITION],
            ]);

            $record->countries()->sync($data[ZoneSchema::COUNTRY_CODES] ?? []);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        return $record->load(ZoneSchema::RES_COUNTRIES);
    }
}
