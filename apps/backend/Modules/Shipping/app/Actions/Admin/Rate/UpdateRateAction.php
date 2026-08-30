<?php

namespace Modules\Shipping\Actions\Admin\Rate;

use Illuminate\Support\Facades\DB;
use Modules\Shipping\Exceptions\Rate\UpdateFailedException;
use Modules\Shipping\Models\Rate;
use Modules\Shipping\Schemas\Rate\RateSchema;
use Throwable;

class UpdateRateAction
{
    /**
     * @throws UpdateFailedException|Throwable
     */
    public function handle(string $id, array $data): Rate
    {
        $record = Rate::query()->findOrFail($id);

        try {
            DB::beginTransaction();

            $record->update([
                RateSchema::METHOD_ID => $data[RateSchema::METHOD_ID],
                RateSchema::ZONE_ID => $data[RateSchema::ZONE_ID],
                RateSchema::PRICE => $data[RateSchema::PRICE],
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new UpdateFailedException;
        }

        return $record;
    }
}
