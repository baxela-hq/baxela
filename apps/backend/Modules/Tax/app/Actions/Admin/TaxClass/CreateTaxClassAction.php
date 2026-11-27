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
    /** @param  array<string, mixed>  $data */
    public function handle(array $data): TaxClass
    {
        try {
            DB::beginTransaction();

            // The partial unique index allows one default per store, so the
            // incumbent must be demoted before the new row is inserted.
            if ($data[TaxClassSchema::IS_DEFAULT]) {
                TaxClass::query()->update([TaxClassSchema::IS_DEFAULT => false]);
            }

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
