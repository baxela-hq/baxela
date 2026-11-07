<?php

namespace Modules\Transfer\Actions\Admin\Import;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Exceptions\Transfer\ImportFailedException;
use Modules\Core\Support\Transfer\ModuleTransferRunner;
use Modules\Core\Support\Transfer\TransferFile;
use Modules\Transfer\Support\TransferRegistry;
use RuntimeException;

/**
 * Replay a module transfer file: the run (dry runs included) is persisted
 * as an auditable row and rolled up into a summary. The replay itself
 * lives in the Core runner, shared with the per-module data endpoints;
 * this action resolves the owning module through the registry.
 */
class ImportTransferAction
{
    public function __construct(protected TransferRegistry $registry) {}

    /**
     * @return array<string, mixed>
     *
     * @throws ImportFailedException
     */
    public function handle(string $filename, string $content, TransferStrategyEnum $strategy, bool $dryRun): array
    {
        [$module, $transfer] = $this->resolve($content);

        return app(ModuleTransferRunner::class)->run(
            $filename,
            $content,
            $strategy,
            $dryRun,
            $module,
            $transfer,
        );
    }

    /**
     * @return array{string, ModuleTransferInterface}
     *
     * @throws ImportFailedException with an `unknown_module:{name}` reason
     */
    private function resolve(string $content): array
    {
        try {
            $file = TransferFile::decode($content);
        } catch (RuntimeException $e) {
            throw new ImportFailedException(meta: ['reason' => $e->getMessage()]);
        }

        $module = TransferFile::module($file);

        $transfer = $this->registry->forModule($module);
        if ($transfer === null) {
            throw new ImportFailedException(meta: ['reason' => 'unknown_module:'.$module]);
        }

        return [$module, $transfer];
    }
}
