<?php

namespace Modules\Tax\Actions\Admin\TaxRate;

use Modules\Tax\Exceptions\TaxRate\UpdateFailedException;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;
use Throwable;

class UpdateTaxRateAction
{
    /**
     * @throws UpdateFailedException
     */
    public function handle(string $id, array $data): TaxRate
    {
        $record = TaxRate::query()->findOrFail($id);

        try {
            $record->update([
                TaxRateSchema::NAME => $data[TaxRateSchema::NAME],
                TaxRateSchema::COUNTRY_CODE => $data[TaxRateSchema::COUNTRY_CODE],
                TaxRateSchema::TAX_CLASS_ID => $data[TaxRateSchema::TAX_CLASS_ID],
                TaxRateSchema::RATE => $data[TaxRateSchema::RATE],
                TaxRateSchema::IS_ACTIVE => $data[TaxRateSchema::IS_ACTIVE],
            ]);
        } catch (Throwable $e) {
            report($e);
            throw new UpdateFailedException;
        }

        return $record->load(TaxRateSchema::RELATION_TAX_CLASS);
    }
}
