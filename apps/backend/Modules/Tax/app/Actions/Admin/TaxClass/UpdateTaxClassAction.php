<?php

namespace Modules\Tax\Actions\Admin\TaxClass;

use Illuminate\Support\Facades\DB;
use Modules\Tax\Exceptions\TaxClass\UpdateFailedException;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Support\SyncsClassFlags;
use Throwable;

class UpdateTaxClassAction
{
    /**
     * @throws UpdateFailedException|Throwable
     */
    public function handle(string $id, array $data): TaxClass
    {
        $record = TaxClass::query()->findOrFail($id);

        try {
            DB::beginTransaction();

            $record->update([
                TaxClassSchema::NAME => $data[TaxClassSchema::NAME],
                TaxClassSchema::IS_DEFAULT => $data[TaxClassSchema::IS_DEFAULT],
                TaxClassSchema::IS_SHIPPING => $data[TaxClassSchema::IS_SHIPPING],
            ]);

            SyncsClassFlags::demoteOthers($record);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new UpdateFailedException;
        }

        return $record;
    }
}
