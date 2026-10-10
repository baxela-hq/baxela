<?php

namespace Modules\Content\Http\Controllers\Admin\Featured;

use App\Http\Controllers\Controller;
use Modules\Content\Actions\Admin\Featured\UpdateFeaturedAction;
use Modules\Content\Http\Requests\Admin\Featured\UpdateFeaturedRequest;
use Modules\Content\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Content\Transformers\Admin\Featured\FeaturedResource;

class UpdateFeaturedController extends Controller
{
    public function __construct(protected UpdateFeaturedAction $action) {}

    public function __invoke(UpdateFeaturedRequest $request): FeaturedResource
    {
        $data = $request->validated();

        return new FeaturedResource($this->action->handle(
            $data[FeaturedItemSchema::REQ_POST_IDS] ?? [],
        ));
    }
}
