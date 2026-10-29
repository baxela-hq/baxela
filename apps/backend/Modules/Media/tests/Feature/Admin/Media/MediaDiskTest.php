<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Media\MediaSchema;
use Modules\Media\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

afterEach(function () {
    // Config mutations leak across tests in one process — restore the
    // default disk so later suites record uploads on the expected disk.
    config(['filesystems.default' => env('FILESYSTEM_DISK', 'public')]);
});

/**
 * Multi-disk behavior: the disk recorded on each media row is the one used
 * for URL resolution and deletion — never the app default disk blindly.
 * S3 deployments just set FILESYSTEM_DISK=s3 (see config/filesystems.php).
 */
it('uploads to the configured s3 disk and records it', function () {
    Storage::fake('s3');
    config(['filesystems.default' => 's3']);

    $user = $this->adminUser();
    $this->actingAs($user);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post($this->baseUrl('/media'), [
        MediaSchema::REQ_FILE => UploadedFile::fake()->image('photo.jpg'),
    ]);

    $response->assertCreated();
    $path = $response->json('data.path');

    expect($response->json('data.disk'))->toBe('s3');
    Storage::disk('s3')->assertExists($path);
});

it('resolves the URL against the record disk, not the default disk', function () {
    Storage::fake('s3');

    $user = $this->adminUser();
    $this->actingAs($user);

    $media = Media::query()->create([
        MediaSchema::USER_ID => $user->id,
        MediaSchema::DISK => 's3',
        MediaSchema::PATH => 'catalog/product/cover.jpg',
        MediaSchema::NAME => 'cover',
        MediaSchema::FILENAME => 'cover.jpg',
        MediaSchema::EXTENSION => 'jpg',
        MediaSchema::MIME_TYPE => 'image/jpeg',
        MediaSchema::SIZE => 1234,
    ]);

    $response = $this->withHeaders(['Accept' => 'application/json'])->get($this->baseUrl('/media'));
    $response->assertOk();

    $row = collect($response->json('data'))->firstWhere('id', $media->id);
    expect($row['url'])->toBe(Storage::disk('s3')->url('catalog/product/cover.jpg'));
});

it('deletes the file from the disk the record lives on', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('catalog/product/gone.jpg', 'bytes');

    $user = $this->adminUser();
    $this->actingAs($user);

    $media = Media::query()->create([
        MediaSchema::USER_ID => $user->id,
        MediaSchema::DISK => 's3',
        MediaSchema::PATH => 'catalog/product/gone.jpg',
        MediaSchema::NAME => 'gone',
        MediaSchema::FILENAME => 'gone.jpg',
        MediaSchema::EXTENSION => 'jpg',
        MediaSchema::MIME_TYPE => 'image/jpeg',
        MediaSchema::SIZE => 5,
    ]);

    $this->withHeaders(['Accept' => 'application/json'])
        ->delete($this->baseUrl('/media/'.$media->id))
        ->assertNoContent();

    Storage::disk('s3')->assertMissing('catalog/product/gone.jpg');
    expect(Media::query()->whereKey($media->id)->exists())->toBeFalse();
});
