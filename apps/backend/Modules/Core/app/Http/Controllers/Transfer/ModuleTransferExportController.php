<?php

namespace Modules\Core\Http\Controllers\Transfer;

use App\Http\Controllers\Controller;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Support\Transfer\ModuleTransferRunner;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base for a module's own export endpoint (`{module}/admin/data/export`):
 * streams the module's transfer file, with the whole file plane handled
 * here — the module only binds its name and its transfer implementation.
 * Modules subclass and keep `__invoke` inherited.
 */
abstract class ModuleTransferExportController extends Controller
{
    /**
     * The module's lower name, as written in the transfer envelope.
     */
    abstract protected function moduleName(): string;

    abstract protected function transfer(): ModuleTransferInterface;

    public function __invoke(): Response
    {
        $payload = app(ModuleTransferRunner::class)->envelope($this->transfer(), $this->moduleName());

        return response()->streamDownload(
            function () use ($payload): void {
                echo json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
            },
            'transfer-'.$this->moduleName().'-'.now()->format('Ymd-His').'.json',
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }
}
