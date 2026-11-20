<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Support\Transfer\InstallationIdentity;

/**
 * Rotate this installation's OUTBOUND transfer identity (C1): exports
 * from now on declare the fresh uuid as their `source_installation`.
 *
 * The mandatory clone-init step — run after booting a database clone
 * (snapshot, staging-from-prod, pre-populated image) so the clone stops
 * exporting under its origin's name. Inbound remap history is deliberately
 * untouched: the cloned database also holds the local rows those mappings
 * point at, so they remain valid. Clearing a source namespace is a
 * separate, explicit operation (`transfer:clear-remaps`).
 */
class TransferResetIdentityCommand extends Command
{
    protected $signature = 'transfer:reset-identity';

    protected $description = 'Rotate the outbound transfer identity stamped into exported files (clone-init step); inbound remap history is untouched';

    public function handle(InstallationIdentity $identity): int
    {
        $previous = $identity->current();
        $uuid = $identity->rotate();

        $this->components->info('Transfer identity rotated.');
        $this->line("  previous: <comment>{$previous}</comment>");
        $this->line("  current:  <info>{$uuid}</info>");
        $this->line('  Inbound remap history was left intact (it is keyed by source installation, not by this uuid).');

        return self::SUCCESS;
    }
}
