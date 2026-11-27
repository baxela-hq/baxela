<?php

namespace Modules\Tax\Actions\Admin\TaxClass;

use Illuminate\Support\Facades\DB;
use Modules\Tax\Exceptions\TaxClass\DeleteDefaultException;
use Modules\Tax\Exceptions\TaxClass\UpdateFailedException;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Support\SyncsClassFlags;
use Throwable;

class UpdateTaxClassAction
{
    /**
     * @throws DeleteDefaultException|UpdateFailedException|Throwable
     */
    /** @param  array<string, mixed>  $data */
    public function handle(string $id, array $data): TaxClass
    {
        $record = TaxClass::query()->findOrFail($id);

        // Demoting the incumbent is only legal as the other half of a
        // replacement's promotion (that action demotes it inside the same
        // transaction). A direct unset would leave the store with no
        // default at all — §6.7.1's at-least-one guard, row 106.
        if ($record->{TaxClassSchema::IS_DEFAULT} && ! $data[TaxClassSchema::IS_DEFAULT]) {
            throw new DeleteDefaultException;
        }

        try {
            DB::beginTransaction();

            // Promote only after the incumbent is demoted: the partial
            // unique index rejects two defaults in the same store.
            if ($data[TaxClassSchema::IS_DEFAULT]) {
                TaxClass::query()->whereKeyNot($id)->update([TaxClassSchema::IS_DEFAULT => false]);
            }

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
