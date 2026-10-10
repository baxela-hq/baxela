<?php

namespace Modules\Content\Http\Controllers\Admin\Featured;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Actions\Admin\Featured\ListFeaturedAction;
use Modules\Content\Transformers\Admin\Featured\FeaturedResource;

class ListFeaturedController extends Controller
{
    public function __construct(protected ListFeaturedAction $action) {}

    public function __invoke(Request $request): FeaturedResource
    {
        return new FeaturedResource($this->action->handle());
    }
}
