<?php

namespace Modules\Catalog\Http\Controllers\Admin\DataTransfer;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\DataTransfer\PreviewCatalogImportAction;
use Modules\Catalog\Exceptions\Data\ImportFailedException;
use Modules\Catalog\Http\Requests\Admin\DataTransfer\PreviewCatalogImportRequest;
use Modules\Catalog\Transformers\Admin\DataTransfer\CatalogImportPreviewResource;

class PreviewCatalogImportController extends Controller
{
    public function __construct(protected PreviewCatalogImportAction $action) {}

    /**
     * @throws ImportFailedException
     */
    public function __invoke(PreviewCatalogImportRequest $request): CatalogImportPreviewResource
    {
        return CatalogImportPreviewResource::make(
            $this->action->handle((int) $request->validated()['media_id'])
        );
    }
}
