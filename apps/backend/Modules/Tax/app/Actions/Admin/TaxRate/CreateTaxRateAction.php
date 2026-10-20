<?php

namespace Modules\Tax\Actions\Admin\TaxRate;

use Modules\Tax\Exceptions\TaxRate\CreationFailedException;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;
use Throwable;

class CreateTaxRateAction
{
    /**
     * @throws CreationFailedException
     */
    public function handle(array $data): TaxRate
    {
        try {
            $record = TaxRate::query()->create([
                TaxRateSchema::NAME => $data[TaxRateSchema::NAME],
                TaxRateSchema::COUNTRY_CODE => $data[TaxRateSchema::COUNTRY_CODE],
                TaxRateSchema::TAX_CLASS_ID => $data[TaxRateSchema::TAX_CLASS_ID],
                TaxRateSchema::RATE => $data[TaxRateSchema::RATE],
                TaxRateSchema::IS_ACTIVE => $data[TaxRateSchema::IS_ACTIVE],
            ]);
        } catch (Throwable $e) {
            report($e);
            throw new CreationFailedException;
        }

        return $record->load(TaxRateSchema::RELATION_TAX_CLASS);
    }
}
