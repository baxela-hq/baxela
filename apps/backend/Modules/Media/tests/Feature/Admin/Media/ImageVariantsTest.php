<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Actions\Admin\Media\GenerateMediaVariantsAction;
use Modules\Media\Gateways\MediaGateway;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Media\MediaSchema;
use Modules\Media\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * A real encoded image (intervention/GD) — the generator reads and decodes
 * the stored bytes, so fixtures must be actual images.
 */
function realImage(int $width, int $height, string $format = 'jpg'): string
{
    // Raw GD: fixtures must be real encoded images; intervention's API is
    // what the generator itself uses, so fixtures stay independent of it.
    $canvas = imagecreatetruecolor($width, $height);
    $background = imagecolorallocate($canvas, 120, 140, 160);
    imagefilledrectangle($canvas, 0, 0, $width, $height, $background);

    ob_start();

    if ($format === 'png') {
        imagepng($canvas);
    } else {
        imagejpeg($canvas, null, 85);
    }

    $contents = (string) ob_get_clean();
    imagedestroy($canvas);

    return $contents;
}

/**
 * A media row whose bytes live on the fake disk; mime defaults to jpeg.
 */
function mediaWithBytes(string $contents, array $overrides = []): Media
{
    Storage::fake('public');

    $disk = Storage::disk('public');
    $disk->put('images/source.jpg', $contents);

    // user_id 1 = the first adminUser() in a fresh (RefreshDatabase) test —
    // delete/patch actions scope by owner.
    return Media::factory()->create(array_merge([
        MediaSchema::USER_ID => 1,
        MediaSchema::DISK => 'public',
        MediaSchema::PATH => 'images/source.jpg',
        MediaSchema::FILENAME => 'source.jpg',
        MediaSchema::EXTENSION => 'jpg',
        MediaSchema::MIME_TYPE => 'image/jpeg',
        MediaSchema::SIZE => strlen($contents),
    ], $overrides));
}

function variantsOf(Media $media): array
{
    return (array) ($media->refresh()->{MediaSchema::METADATA}['variants'] ?? []);
}

it('generates the configured presets on upload (listener runs inline)', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    $this->actingAs($this->adminUser());

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->post($this->baseUrl('/media'), [
            MediaSchema::REQ_FILE => UploadedFile::fake()->createWithContent(
                'photo.jpg',
                realImage(1000, 600),
            ),
        ])->assertCreated();

    // QUEUE_CONNECTION=sync: the listener ran inside the upload request —
    // after the response payload was serialized, so re-fetch the row.
    $metadata = Media::query()->find($response->json('data.id'))?->{MediaSchema::METADATA} ?? [];

    expect($metadata['version'])->toBe(1)
        ->and($metadata['width'])->toBe(1000)
        ->and($metadata['height'])->toBe(600)
        ->and(array_keys($metadata['variants']))->toEqualCanonicalizing(['thumb', 'medium', 'large']);

    $disk = Storage::disk('public');

    foreach ($metadata['variants'] as $preset => $variant) {
        expect($variant['status'])->toBe('completed');
        $disk->assertExists($variant['path']);
        // Never upscaled: the thumb of a 1000px original is 400, but a
        // 300px original's thumb would be 300 — widths never exceed the
        // source and always match the stored metadata.
        expect($variant['width'])->toBeLessThanOrEqual(1000);
    }

    expect($metadata['variants']['thumb']['width'])->toBe(400)
        ->and($metadata['variants']['medium']['width'])->toBe(800)
        // large clamps to the 1000px source (1600 > 1000, no upscale)
        ->and($metadata['variants']['large']['width'])->toBe(1000);
});

it('is idempotent: an unchanged config is a no-op with identical files', function () {
    $media = mediaWithBytes(realImage(1200, 800));
    $action = app(GenerateMediaVariantsAction::class);

    expect($action->handle($media))->toBe('generated');

    $before = variantsOf($media);
    $paths = array_column($before, 'path');

    expect($action->handle($media))->toBe('skipped')
        ->and(variantsOf($media))->toBe($before)
        ->and($media->refresh()->{MediaSchema::METADATA}['version'])->toBe(1);

    foreach ($paths as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

it('regenerates under a changed config and prunes superseded files', function () {
    $media = mediaWithBytes(realImage(1200, 800));
    $action = app(GenerateMediaVariantsAction::class);
    $action->handle($media);

    $oldPaths = array_column(variantsOf($media), 'path');
    $oldVersion = $media->refresh()->{MediaSchema::METADATA}['version'];

    config(['media.images.presets' => ['thumb' => ['width' => 200]]]);

    expect($action->handle($media))->toBe('generated');

    $new = variantsOf($media);

    expect($media->refresh()->{MediaSchema::METADATA}['version'])->toBe($oldVersion + 1)
        ->and(array_keys($new))->toBe(['thumb'])
        ->and($new['thumb']['width'])->toBe(200);

    // Superseded files pruned after publication.
    foreach ($oldPaths as $path) {
        Storage::disk('public')->assertMissing($path);
    }
    Storage::disk('public')->assertExists($new['thumb']['path']);
});

it('marks a corrupt source as a permanent failure and publishes nothing', function () {
    $media = mediaWithBytes('definitely-not-an-image');

    expect(app(GenerateMediaVariantsAction::class)->handle($media))->toBe('failed-permanent')
        ->and(variantsOf($media))->toBe([])
        // The original still serves.
        ->and(Storage::disk('public')->exists($media->{MediaSchema::PATH}))->toBeTrue();
});

it('rejects sources beyond the configured dimension limits', function () {
    $media = mediaWithBytes(realImage(8100, 20));

    expect(app(GenerateMediaVariantsAction::class)->handle($media))->toBe('failed-permanent')
        ->and(variantsOf($media))->toBe([]);
});

it('skips gif sources without touching metadata', function () {
    $canvas = imagecreatetruecolor(300, 200);
    ob_start();
    imagegif($canvas);
    $gif = (string) ob_get_clean();
    imagedestroy($canvas);

    $media = mediaWithBytes($gif, [MediaSchema::MIME_TYPE => 'image/gif']);

    expect(app(GenerateMediaVariantsAction::class)->handle($media))->toBe('skipped')
        ->and(variantsOf($media))->toBe([]);
});

it('writes variants to the record disk (s3) and resolves urls against it', function () {
    Storage::fake('s3');

    $contents = realImage(1000, 600);
    Storage::disk('s3')->put('images/source.jpg', $contents);

    $media = Media::factory()->create([
        MediaSchema::DISK => 's3',
        MediaSchema::PATH => 'images/source.jpg',
        MediaSchema::FILENAME => 'source.jpg',
        MediaSchema::EXTENSION => 'jpg',
        MediaSchema::MIME_TYPE => 'image/jpeg',
        MediaSchema::SIZE => strlen($contents),
    ]);

    expect(app(GenerateMediaVariantsAction::class)->handle($media))->toBe('generated');

    $variants = variantsOf($media);

    // The gateway resolves variant urls against the record's disk.
    $resolved = app(MediaGateway::class)
        ->getVariantsByIds([$media->getKey()])[$media->getKey()] ?? [];

    foreach ($variants as $name => $variant) {
        Storage::disk('s3')->assertExists($variant['path']);
        Storage::disk('public')->assertMissing($variant['path']);
        expect($resolved[$name]['url'])->toContain($variant['path']);
    }
});

it('refuses deletion while generation holds the lock, then deletes original and variants', function () {
    $media = mediaWithBytes(realImage(1000, 600));
    app(GenerateMediaVariantsAction::class)->handle($media);

    $variantPaths = array_column(variantsOf($media), 'path');
    $this->actingAs($this->adminUser());

    // A held lock (generation in progress) defers the deletion.
    $generationLock = Cache::lock('media-variants:'.$media->getKey(), 10);
    $generationLock->get();

    expect($this->deleteJson($this->baseUrl('/media/'.$media->id)))->assertNoContent()
        ->and(Media::query()->find($media->id))->not->toBeNull()
        ->and(Storage::disk('public')->exists($media->{MediaSchema::PATH}))->toBeTrue();

    $generationLock->release();

    // Lock free: deletion removes the row, the original and every variant.
    expect($this->deleteJson($this->baseUrl('/media/'.$media->id)))->assertNoContent()
        ->and(Media::query()->find($media->id))->toBeNull();

    Storage::disk('public')->assertMissing($media->{MediaSchema::PATH});
    foreach ($variantPaths as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});

it('is a no-op when generation runs for a deleted media row', function () {
    $media = mediaWithBytes(realImage(500, 500));
    $id = $media->getKey();
    $media->delete();

    $ghost = new Media;
    $ghost->id = $id;
    $ghost->{MediaSchema::DISK} = 'public';
    $ghost->{MediaSchema::PATH} = 'images/source.jpg';
    $ghost->{MediaSchema::MIME_TYPE} = 'image/jpeg';

    expect(app(GenerateMediaVariantsAction::class)->handle($ghost))->toBe('no-op');
});

it('bumps the version when regeneration is forced', function () {
    $media = mediaWithBytes(realImage(1000, 600));
    $action = app(GenerateMediaVariantsAction::class);
    $action->handle($media);

    expect($action->handle($media, force: true))->toBe('generated')
        ->and($media->refresh()->{MediaSchema::METADATA}['version'])->toBe(2);
});
