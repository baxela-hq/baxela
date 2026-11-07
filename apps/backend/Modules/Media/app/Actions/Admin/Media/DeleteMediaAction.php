<?php

namespace Modules\Media\Actions\Admin\Media;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Contracts\Events\Media\MediaDeletedEvent;
use Modules\Core\Utils\Auth;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Media\MediaSchema;

class DeleteMediaAction extends AbstractMediaAction
{
    public function handle(string $id): bool
    {
        $record = $this->model->query()
            ->where(MediaSchema::USER_ID, Auth::id())
            ->findOrFail($id);

        try {
            // The per-media lock is shared with variant generation
            // (gap-C3 D10): generate-vs-delete can never interleave. A
            // deletion is refused while generation holds the lock — the
            // admin retries once the (bounded, ≤ job timeout) generation
            // finishes.
            $lock = Cache::lock('media-variants:'.$record->getKey(), 15);

            if (! $lock->get()) {
                report(new \RuntimeException('media delete deferred: generation in progress'));

                return false;
            }

            try {
                $disk = Storage::disk($record->{MediaSchema::DISK});

                $disk->delete($this->storedPaths($record));

                $delete = $record->delete();
                if ($delete) {
                    event(MediaDeletedEvent::fill($record->toArray()));
                }

                return $delete;
            } finally {
                $lock->release();
            }
        } catch (\Throwable $t) {
            report($t);

            return false;
        }
    }

    /**
     * The original plus every variant file recorded in metadata, across
     * all generation versions.
     *
     * @return list<string>
     */
    private function storedPaths(Media $record): array
    {
        $paths = [(string) $record->{MediaSchema::PATH}];

        foreach ((array) ($record->{MediaSchema::METADATA}['variants'] ?? []) as $variant) {
            if (! empty($variant['path'])) {
                $paths[] = (string) $variant['path'];
            }
        }

        return array_values(array_unique($paths));
    }
}
