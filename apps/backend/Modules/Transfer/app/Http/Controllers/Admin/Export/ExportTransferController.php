<?php

namespace Modules\Transfer\Http\Controllers\Admin\Export;

use App\Http\Controllers\Controller;
use Modules\Transfer\Actions\Admin\Export\ExportTransferAction;
use Modules\Transfer\Exceptions\Transfer\ExportFailedException;
use Modules\Transfer\Exceptions\Transfer\ModuleNotSupportedException;
use Symfony\Component\HttpFoundation\Response;

class ExportTransferController extends Controller
{
    public function __construct(protected ExportTransferAction $action) {}

    /**
     * Whole-module JSON backup: request-shaped payloads in dependency
     * order, streamed as a download.
     *
     * @throws ModuleNotSupportedException
     * @throws ExportFailedException
     */
    public function __invoke(string $module): Response
    {
        $payload = $this->action->handle($module);

        return response()->streamDownload(
            function () use ($payload): void {
                echo json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
            },
            'transfer-'.$module.'-'.now()->format('Ymd-His').'.json',
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }
}
