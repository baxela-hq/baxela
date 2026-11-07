<?php

return [
    // Image variant generation (gap-C3). Presets are width targets; the
    // generator never upscales — a preset wider than the original is
    // clamped to the original's intrinsic width, and the advertised
    // srcset/width always reflects the real output.
    'enabled' => true,

    'presets' => [
        'thumb' => ['width' => 400],
        'medium' => ['width' => 800],
        'large' => ['width' => 1600],
    ],

    'quality' => 82,

    // Source MIME types that get variants. GIF is deliberately excluded
    // (animated gifs must not be re-encoded), svg is vector (nothing to
    // rasterize), and the list must stay within the upload MIME enum.
    'formats' => [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ],

    // Source-image guards, checked before any full decode. The pixel
    // budget accounts for GD's decoded footprint (~4.6 bytes/px) plus
    // intermediate copies across concurrent jobs — size PHP memory_limit
    // and worker concurrency against it (gap-C3 D3).
    'max_width' => 8000,
    'max_height' => 8000,
    'max_pixels' => 20_000_000,

    // Per-media lock TTL must stay safely above the generation job
    // timeout (gap-C3 D5: TTL ≥ 1.5 × timeout), so a live worker can
    // never be lock-expired mid-write.
    'lock_ttl_seconds' => 900,
    'job_timeout_seconds' => 600,
];
