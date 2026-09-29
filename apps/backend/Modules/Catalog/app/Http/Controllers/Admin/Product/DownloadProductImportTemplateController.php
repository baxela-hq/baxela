<?php

namespace Modules\Catalog\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Product\DownloadImportTemplateAction;
use Symfony\Component\HttpFoundation\Response;

class DownloadProductImportTemplateController extends Controller
{
    public function __construct(protected DownloadImportTemplateAction $action) {}

    /**
     * Example CSV, UTF-8 with BOM so Excel re-opens Persian content.
     */
    public function __invoke(): Response
    {
        return response()->make($this->action->handle(), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="product-import-template.csv"',
        ]);
    }
}
