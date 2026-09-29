<?php

namespace Modules\Catalog\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Product\PreviewProductImportAction;
use Modules\Catalog\Exceptions\Product\ImportFailedException;
use Modules\Catalog\Http\Requests\Admin\Product\PreviewImportRequest;
use Modules\Catalog\Transformers\Admin\Product\ProductImportPreviewResource;

class PreviewProductImportController extends Controller
{
    public function __construct(protected PreviewProductImportAction $action) {}

    /**
     * @throws ImportFailedException
     */
    public function __invoke(PreviewImportRequest $request): ProductImportPreviewResource
    {
        return ProductImportPreviewResource::make(
            $this->action->handle((int) $request->validated()['media_id'])
        );
    }
}
