<?php

namespace Modules\Catalog\Http\Controllers\Admin\Featured;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Featured\UpdateFeaturedAction;
use Modules\Catalog\Http\Requests\Admin\Featured\UpdateFeaturedRequest;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Catalog\Transformers\Admin\Featured\FeaturedResource;

class UpdateFeaturedController extends Controller
{
    public function __construct(protected UpdateFeaturedAction $action) {}

    public function __invoke(UpdateFeaturedRequest $request): FeaturedResource
    {
        $data = $request->validated();

        return new FeaturedResource($this->action->handle(
            $data[FeaturedItemSchema::REQ_PRODUCT_IDS] ?? [],
            $data[FeaturedItemSchema::REQ_CATEGORY_IDS] ?? [],
        ));
    }
}
