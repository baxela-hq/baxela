<?php

namespace Modules\Core\Contracts\Gateways\Transfer\DTOs;

use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;

/**
 * How a section's rows are replayed: the duplicate strategy applied to
 * rows matching an existing record by natural key, and whether the run
 * is a dry run (validation and matching only, no writes).
 *
 * The C2 reference-resolution context rides along: the importing file's
 * source-installation namespace (remap tier + raw-id tier behavior) and
 * whether the request explicitly allowed raw-id fallback for legacy
 * unidentified files.
 */
final class ImportOptions
{
    public function __construct(
        public readonly TransferStrategyEnum $strategy = TransferStrategyEnum::UPDATE,
        public readonly bool $dryRun = false,
        public readonly string $sourceInstallation = '',
        public readonly bool $identified = false,
        public readonly bool $allowRawIds = false,
    ) {}
}
