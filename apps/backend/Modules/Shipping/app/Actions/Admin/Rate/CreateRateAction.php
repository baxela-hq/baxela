<?php

namespace Modules\Shipping\Actions\Admin\Rate;

use Illuminate\Support\Facades\DB;
use Modules\Shipping\Exceptions\Rate\CreationFailedException;
use Modules\Shipping\Models\Rate;
use Modules\Shipping\Schemas\Rate\RateSchema;
use Throwable;

class CreateRateAction
{
    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(array $data): Rate
    {
        try {
            DB::beginTransaction();

            $record = Rate::query()->create([
                RateSchema::METHOD_ID => $data[RateSchema::METHOD_ID],
                RateSchema::ZONE_ID => $data[RateSchema::ZONE_ID],
                RateSchema::PRICE => $data[RateSchema::PRICE],
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        return $record;
    }
}
