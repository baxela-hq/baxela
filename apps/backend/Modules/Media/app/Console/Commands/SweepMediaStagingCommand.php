<?php

namespace Modules\Media\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Schemas\MediaOutbox\MediaOutboxSchema;
use Throwable;

/**
 * The media staging sweep (postgres-migration §3.3.1): cleanup only —
 * NEVER publication fencing (the claim-conditioned terminal transition is
 * the authority). A staging file is deletable iff (a) its age exceeds the
 * grace window AND (b) it is not referenced by a non-terminal outbox row.
 * A live processor's in-flight staging is protected by its entry's
 * non-terminal status; an abandoned one becomes eligible only after both
 * the entry resolves (or its claim lapses past grace) and the file ages
 * out. Deletions are logged; the sweep is idempotent.
 */
final class SweepMediaStagingCommand extends Command
{
    protected $signature = 'media:outbox:sweep
        {--grace= : Staging grace window in hours (default 24)}
        {--dry : List candidates without deleting}';

    protected $description = 'Delete aged, unreferenced media staging files (cleanup, not fencing)';

    public function handle(): int
    {
        $disk = (string) config('filesystems.default');
        $graceHours = max(1, (int) ($this->option('grace') ?? 24));
        $cutoff = now()->subHours($graceHours);

        // Installation-wide maintenance scan across all stores, not tenant
        // CRUD: non-terminal staging paths stay untouchable, whatever their age.
        $referenced = DB::table(MediaOutboxSchema::TABLE)
            ->whereIn(MediaOutboxSchema::STATUS, [
                MediaOutboxSchema::STATUS_PENDING,
                MediaOutboxSchema::STATUS_PROCESSING,
            ])
            ->pluck(MediaOutboxSchema::STAGING_PATH)
            ->flip();

        $deleted = 0;

        try {
            $files = Storage::disk($disk)->files('.staging/media');
        } catch (Throwable) {
            $this->warn('Staging prefix is absent — nothing to sweep.');

            return self::SUCCESS;
        }

        foreach ($files as $path) {
            if ($referenced->has($path)) {
                continue;
            }

            $modified = (int) Storage::disk($disk)->lastModified($path);

            if (now()->timestamp - $modified < $graceHours * 3600) {
                continue; // Not aged out yet (covers the grace for fresh claims).
            }

            if ($this->option('dry')) {
                $this->line("would delete: {$path}");

                continue;
            }

            Storage::disk($disk)->delete($path);
            $deleted++;
        }

        $this->info(sprintf('Swept %d staging file(s); cutoff %s.', $deleted, $cutoff->toDateTimeString()));

        return self::SUCCESS;
    }
}
