<?php

namespace Modules\Core\Http\Controllers\Public\Country;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Core\Actions\Public\Country\ListCountryAction;
use Modules\Core\Transformers\Public\Country\CountryResource;

class ListCountryController extends Controller
{
    public function __construct(protected ListCountryAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return CountryResource::collection($this->action->handle());
    }
}
