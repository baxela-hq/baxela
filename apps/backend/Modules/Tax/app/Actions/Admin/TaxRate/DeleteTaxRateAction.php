<?php

namespace Modules\Tax\Actions\Admin\TaxRate;

use Modules\Tax\Models\TaxRate;

class DeleteTaxRateAction
{
    public function handle(string $id): bool
    {
        $record = TaxRate::query()->findOrFail($id);

        return (bool) $record->delete();
    }
}
