<?php

namespace Modules\Catalog\Http\Controllers\Admin\DataTransfer;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\DataTransfer\ExportCatalogDataAction;
use Symfony\Component\HttpFoundation\Response;

class ExportCatalogDataController extends Controller
{
    public function __construct(protected ExportCatalogDataAction $action) {}

    /**
     * Whole-module JSON backup: request-shaped payloads in dependency
     * order, streamed as a download.
     */
    public function __invoke(): Response
    {
        $payload = $this->action->handle();

        return response()->streamDownload(
            function () use ($payload): void {
                echo json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
            },
            'catalog-'.now()->format('Ymd-His').'.json',
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }
}
