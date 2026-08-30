<?php

namespace Modules\Core\Http\Controllers\Admin\Country;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Core\Actions\Admin\Country\ListCountryAction;
use Modules\Core\Transformers\Admin\Country\CountryResource;

class ListCountryController extends Controller
{
    public function __construct(protected ListCountryAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return CountryResource::collection($this->action->handle());
    }
}
