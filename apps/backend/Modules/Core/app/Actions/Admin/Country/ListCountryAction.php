<?php

namespace Modules\Core\Actions\Admin\Country;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Models\Country;
use Modules\Core\Schemas\Country\CountrySchema;

class ListCountryAction
{
    public function handle(): Collection
    {
        return Country::query()
            ->orderBy(CountrySchema::NAME)
            ->get();
    }
}
