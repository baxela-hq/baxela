<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Models\TransferRemap;
use Modules\Core\Schemas\TransferRemap\TransferRemapSchema;
use Modules\Core\Support\Transfer\TransferFile;

/**
 * Explicitly delete the inbound remap history of ONE source installation
 * (C1): the escape hatch for operators who truly need it — e.g. cleaning
 * up after test imports — never a side effect of anything else. Confirm
 * gated, because those mappings are what makes a re-import idempotent.
 */
class TransferClearRemapsCommand extends Command
{
    protected $signature = 'transfer:clear-remaps
        {--source= : The source installation uuid whose mappings to delete (required)}
        {--confirm : Actually delete; without it the command only reports}';

    protected $description = 'Delete the persisted transfer remaps of one source installation (requires --confirm)';

    public function handle(): int
    {
        $source = (string) $this->option('source');

        if (! TransferFile::isValidSourceInstallation($source)) {
            $this->error('A valid source installation uuid is required (--source=...).');

            return self::FAILURE;
        }

        $count = TransferRemap::query()
            ->where(TransferRemapSchema::SOURCE_INSTALLATION, $source)
            ->count();

        if ((bool) $this->option('confirm') === false) {
            $this->line("Would delete <comment>{$count}</comment> remap(s) for source <comment>{$source}</comment>. Re-run with --confirm.");

            return self::SUCCESS;
        }

        TransferRemap::query()
            ->where(TransferRemapSchema::SOURCE_INSTALLATION, $source)
            ->delete();

        $this->components->info("Deleted {$count} remap(s) for source {$source}.");

        return self::SUCCESS;
    }
}
