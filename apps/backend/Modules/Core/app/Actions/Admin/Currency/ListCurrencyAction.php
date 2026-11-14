<?php

namespace Modules\Core\Actions\Admin\Currency;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Models\Currency;
use Modules\Core\Schemas\Currency\CurrencySchema;

class ListCurrencyAction
{
    /**
     * Active currencies by default (the settings page's default-currency
     * dropdown consumes this); the management pages ask for all rows to
     * reveal and toggle inactive ones.
     */
    public function handle(bool $all = false): Collection
    {
        $query = Currency::query()
            ->orderBy(CurrencySchema::IS_DEFAULT, 'desc');

        if (! $all) {
            $query->where(CurrencySchema::IS_ACTIVE, true);
        }

        return $query->get();
    }
}
