<?php

namespace Modules\Core\Actions\Public\Country;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Models\Country;
use Modules\Core\Schemas\Country\CountrySchema;

class ListCountryAction
{
    /** @return Collection<int, Country> */
    public function handle(): Collection
    {
        return Country::query()
            ->orderBy(CountrySchema::NAME)
            ->get();
    }
}
