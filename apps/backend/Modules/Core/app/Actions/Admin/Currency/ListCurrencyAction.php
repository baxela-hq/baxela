<?php

namespace Modules\Core\Actions\Admin\Currency;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Models\Currency;
use Modules\Core\Schemas\Currency\CurrencySchema;

class ListCurrencyAction
{
    public function handle(): Collection
    {
        return Currency::query()
            ->orderBy(CurrencySchema::IS_DEFAULT, 'desc')
            ->get();
    }
}
