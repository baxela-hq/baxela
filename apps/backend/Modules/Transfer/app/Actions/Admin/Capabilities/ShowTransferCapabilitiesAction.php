<?php

namespace Modules\Transfer\Actions\Admin\Capabilities;

use Modules\Transfer\Support\TransferRegistry;

/**
 * The coverage matrix of the data-transfer standard: every enabled
 * module with the sections it exposes for export/import — empty for
 * modules that have not opted in, so admins see exactly where the
 * platform stands.
 */
class ShowTransferCapabilitiesAction
{
    public function __construct(protected TransferRegistry $registry) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        $capabilities = [];
        foreach ($this->registry->enabledModuleNames() as $module) {
            $transfer = $this->registry->forModule($module);

            $sections = [];
            if ($transfer !== null) {
                foreach ($transfer->sections() as $section) {
                    $sections[] = [
                        'key' => $section->key,
                        'exportable' => $section->exportable,
                        'importable' => $section->importable,
                    ];
                }
            }

            $capabilities[] = [
                'module' => $module,
                'supported' => $transfer !== null,
                'sections' => $sections,
            ];
        }

        return $capabilities;
    }
}
