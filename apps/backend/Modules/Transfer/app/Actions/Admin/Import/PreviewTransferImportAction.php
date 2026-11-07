<?php

namespace Modules\Transfer\Actions\Admin\Import;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Exceptions\Transfer\ImportFailedException;
use Modules\Core\Support\Transfer\ModuleTransferRunner;
use Modules\Core\Support\Transfer\TransferFile;
use Modules\Transfer\Support\TransferRegistry;
use RuntimeException;

/**
 * Structure check of an uploaded transfer file against the module the
 * file declares. The file plane lives in the Core runner; this action
 * resolves the owning module through the registry.
 */
class PreviewTransferImportAction
{
    public function __construct(protected TransferRegistry $registry) {}

    /**
     * @return array<string, mixed>
     *
     * @throws ImportFailedException
     */
    public function handle(string $filename, string $content): array
    {
        [$module, $transfer] = $this->resolve($content);

        return app(ModuleTransferRunner::class)->preview($filename, $content, $module, $transfer);
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
