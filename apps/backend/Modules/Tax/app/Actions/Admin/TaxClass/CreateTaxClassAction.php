<?php

namespace Modules\Tax\Actions\Admin\TaxClass;

use Illuminate\Support\Facades\DB;
use Modules\Tax\Exceptions\TaxClass\CreationFailedException;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Support\SyncsClassFlags;
use Throwable;

class CreateTaxClassAction
{
    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(array $data): TaxClass
    {
        try {
            DB::beginTransaction();

            $record = TaxClass::query()->create([
                TaxClassSchema::NAME => $data[TaxClassSchema::NAME],
                TaxClassSchema::IS_DEFAULT => $data[TaxClassSchema::IS_DEFAULT],
                TaxClassSchema::IS_SHIPPING => $data[TaxClassSchema::IS_SHIPPING],
            ]);

            SyncsClassFlags::demoteOthers($record);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        return $record;
    }
}
