<?php

namespace Modules\Media\Actions\Admin\Media;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Media\MediaSchema;

/**
 * Generates the configured image presets for one media record (gap-C3).
 *
 * Guarantees:
 * - **Locked:** a per-media atomic lock (TTL ≥ 1.5× the job timeout)
 *   serializes generation against itself and against deletion; a job that
 *   cannot acquire it reports "locked" and is re-released by the listener.
 * - **Idempotent:** a config fingerprint in metadata — duplicate events
 *   and unchanged configs are no-ops; regeneration (new fingerprint or
 *   forced) bumps the generation version.
 * - **Atomic publication:** candidate metadata is published in ONE row
 *   update. Completed presets replace their entry; a preset that failed
 *   this attempt keeps its previous published entry (never regresses);
 *   presets without a previous published entry appear only when
 *   completed. Old-version files are pruned only after publication.
 * - **Failure classification:** corrupt/oversized source = permanent
 *   (nothing published, original serves); storage outage = retryable
 *   (exception escapes to the queue); single-preset failure = partial
 *   (logged, other presets stand).
 */
class GenerateMediaVariantsAction
{
    /**
     * @return string one of: generated|skipped|no-op|locked|failed-permanent
     */
    public function handle(Media $media, bool $force = false): string
    {
        $lock = Cache::lock($this->lockKey($media), (int) config('media.images.lock_ttl_seconds'));

        if (! $lock->get()) {
            return 'locked';
        }

        try {
            return $this->generate($media, $force);
        } finally {
            $lock->release();
        }
    }

    private function generate(Media $media, bool $force): string
    {
        // Re-check inside the lock: the record may have been deleted while
        // this job waited.
        /** @var Media|null $fresh */
        $fresh = Media::query()->find($media->getKey());

        if (is_null($fresh)) {
            return 'no-op';
        }

        $mime = $fresh->{MediaSchema::MIME_TYPE}->value;

        if (! in_array($mime, (array) config('media.images.formats'), true)) {
            return 'skipped';
        }

        $metadata = (array) ($fresh->{MediaSchema::METADATA} ?? []);
        $fingerprint = md5((string) json_encode([
            'presets' => config('media.images.presets'),
            'quality' => config('media.images.quality'),
        ]));

        if (! $force
            && ($metadata['variants_fingerprint'] ?? null) === $fingerprint
            && ($metadata['variants'] ?? []) !== []) {
            return 'skipped';
        }

        $disk = Storage::disk($fresh->{MediaSchema::DISK});

        try {
            $contents = $disk->get($fresh->{MediaSchema::PATH});
        } catch (\Throwable) {
            // Storage outage is retryable — escape to the queue, nothing
            // marked permanent (D4).
            throw new \RuntimeException('variant source read failed; retryable');
        }

        // Guard dimensions before any full decode (D3).
        $sourceInfo = @getimagesizefromstring($contents);

        if ($sourceInfo === false) {
            report(new \RuntimeException('corrupt image source; permanent'));

            return 'failed-permanent';
        }

        [$sourceWidth, $sourceHeight] = $sourceInfo;

        if ($sourceWidth > (int) config('media.images.max_width')
            || $sourceHeight > (int) config('media.images.max_height')
            || $sourceWidth * $sourceHeight > (int) config('media.images.max_pixels')) {
            report(new \RuntimeException('image exceeds configured dimension limits; permanent'));

            return 'failed-permanent';
        }

        $manager = new ImageManager(new GdDriver);

        $published = (array) ($metadata['variants'] ?? []);
        $version = (int) ($metadata['version'] ?? 0) + 1;
        $candidates = [];
        $oldPaths = array_map(
            fn (array $entry): string => (string) ($entry['path'] ?? ''),
            $published,
        );

        foreach ((array) config('media.images.presets') as $name => $preset) {
            try {
                // Decode per preset: intervention v4's scale mutates the
                // image in place, so a shared instance would shrink each
                // subsequent preset to the previous one's width.
                $image = $manager->decodeBinary($contents);
                $targetWidth = min((int) $preset['width'], $sourceWidth);
                $variant = $image->scaleDown(width: $targetWidth);
                $encoder = $this->encoderFor($mime);
                $encoded = $variant->encode($encoder);
                $variantPath = $this->variantPath(
                    (string) $fresh->{MediaSchema::PATH},
                    (string) $name,
                    $version,
                );

                $disk->put($variantPath, $encoded->toString());

                $candidates[$name] = [
                    'status' => 'completed',
                    'path' => $variantPath,
                    'size' => strlen($encoded->toString()),
                    'width' => $variant->width(),
                    'height' => $variant->height(),
                ];
            } catch (\Throwable $t) {
                // Partial failure: this preset keeps its previous published
                // entry (never regresses); the failure is reported.
                report(new \RuntimeException("preset {$name} failed: {$t->getMessage()}", previous: $t));
            }
        }

        // Every surviving candidate is completed by construction — presets
        // that failed this attempt are simply absent (their previous
        // published entry, if any, keeps serving).
        $completed = $candidates;

        if ($completed === []) {
            // Nothing new completed — the published set never regresses and
            // no absent preset appears (review v2 §3-Problem A).
            return 'failed-permanent';
        }

        $variants = [];
        $newPaths = [];

        foreach ($completed as $name => $entry) {
            $variants[$name] = $entry;
            $newPaths[] = $entry['path'];
        }

        $fresh->forceFill([
            MediaSchema::METADATA => array_merge($metadata, [
                'version' => $version,
                'variants_fingerprint' => $fingerprint,
                'width' => $sourceWidth,
                'height' => $sourceHeight,
                'variants' => $variants,
            ]),
        ])->save();

        // Prune only after a successful publication: superseded presets and
        // files from previous versions.
        foreach ($published as $name => $old) {
            $oldPath = (string) ($old['path'] ?? '');

            if ($oldPath !== '' && ! in_array($oldPath, $newPaths, true)) {
                $disk->delete($oldPath);
            }
        }

        foreach (array_diff($oldPaths, $newPaths) as $stale) {
            if ($stale !== '') {
                $disk->delete($stale);
            }
        }

        return 'generated';
    }

    private function encoderFor(string $mime): object
    {
        $quality = (int) config('media.images.quality');

        return match ($mime) {
            'image/png' => new PngEncoder,
            'image/webp' => new WebpEncoder(quality: $quality),
            default => new JpegEncoder(quality: $quality),
        };
    }

    private function variantPath(string $originalPath, string $preset, int $version): string
    {
        $directory = str_contains($originalPath, '/')
            ? preg_replace('/\/[^\/]+$/', '', $originalPath)
            : '';
        $filename = basename($originalPath);
        $dot = strrpos($filename, '.');

        return ($directory === '' ? '' : $directory.'/')
            .($dot === false ? $filename : substr($filename, 0, $dot))
            ."-{$preset}-v{$version}"
            .($dot === false ? '' : substr($filename, $dot));
    }

    public function lockKey(Media $media): string
    {
        return 'media-variants:'.$media->getKey();
    }
}
