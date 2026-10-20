<?php

namespace Modules\Tax\Support;

use Modules\Tax\Models\TaxClass;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;

class SyncsClassFlags
{
    /**
     * Enforce the single-flag invariants: promoting a default or shipping
     * class demotes every other row. Runs inside the caller's transaction.
     */
    public static function demoteOthers(TaxClass $record): void
    {
        if ($record->{TaxClassSchema::IS_DEFAULT}) {
            TaxClass::query()
                ->whereKeyNot($record->{TaxClassSchema::ID})
                ->update([TaxClassSchema::IS_DEFAULT => false]);
        }

        if ($record->{TaxClassSchema::IS_SHIPPING}) {
            TaxClass::query()
                ->whereKeyNot($record->{TaxClassSchema::ID})
                ->update([TaxClassSchema::IS_SHIPPING => false]);
        }
    }
}
