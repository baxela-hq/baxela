<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Models\Folder;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Folder\FolderSchema;
use Modules\Media\Schemas\Media\MediaSchema;
use Modules\Media\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('lists root media via filter[folder_id]=null', function () {

    $user = $this->adminUser();
    $this->actingAs($user);

    $folder = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => null,
    ]);

    $rootMedia = Media::factory()->create([
        MediaSchema::USER_ID => $user->id,
        MediaSchema::FOLDER_ID => null,
    ]);
    $inFolder = Media::factory()->create([
        MediaSchema::USER_ID => $user->id,
        MediaSchema::FOLDER_ID => $folder->id,
    ]);

    $response = $this->getJson($this->baseUrl('/media?filter[folder_id]=null'));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toHaveCount(1)
        ->and($ids)->toContain($rootMedia->id)
        ->and($ids)->not->toContain($inFolder->id);
});

it('lists media by folder via filter[folder_id]={id}', function () {

    $user = $this->adminUser();
    $this->actingAs($user);

    $folder = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => null,
    ]);

    $inFolder = Media::factory()->create([
        MediaSchema::USER_ID => $user->id,
        MediaSchema::FOLDER_ID => $folder->id,
    ]);
    $rootMedia = Media::factory()->create([
        MediaSchema::USER_ID => $user->id,
        MediaSchema::FOLDER_ID => null,
    ]);

    $response = $this->getJson($this->baseUrl('/media?filter[folder_id]='.$folder->id));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toHaveCount(1)
        ->and($ids)->toContain($inFolder->id)
        ->and($ids)->not->toContain($rootMedia->id);
});

it('renames a media via PATCH', function () {

    $user = $this->adminUser();
    $this->actingAs($user);

    $media = Media::factory()->create([
        MediaSchema::USER_ID => $user->id,
        MediaSchema::FOLDER_ID => null,
    ]);

    $response = $this->patchJson($this->baseUrl('/media/'.$media->id), [
        MediaSchema::NAME => 'new-file',
    ]);

    $response->assertOk();
    expect($response->json('data.name'))->toBe('new-file');
    expect($media->fresh()->name)->toBe('new-file');
});

it('moves a media into a folder via PATCH', function () {

    $user = $this->adminUser();
    $this->actingAs($user);

    $folder = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => null,
    ]);
    $media = Media::factory()->create([
        MediaSchema::USER_ID => $user->id,
        MediaSchema::FOLDER_ID => null,
    ]);

    $response = $this->patchJson($this->baseUrl('/media/'.$media->id), [
        MediaSchema::FOLDER_ID => $folder->id,
    ]);

    $response->assertOk();
    expect($response->json('data.folder_id'))->toBe($folder->id);
    expect($media->fresh()->folder_id)->toBe($folder->id);
});

it('moves a media to root via PATCH with folder_id=null', function () {

    $user = $this->adminUser();
    $this->actingAs($user);

    $folder = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => null,
    ]);
    $media = Media::factory()->create([
        MediaSchema::USER_ID => $user->id,
        MediaSchema::FOLDER_ID => $folder->id,
    ]);

    $response = $this->patchJson($this->baseUrl('/media/'.$media->id), [
        MediaSchema::FOLDER_ID => null,
    ]);

    $response->assertOk();
    expect($response->json('data.folder_id'))->toBeNull();
    expect($media->fresh()->folder_id)->toBeNull();
});

it('scopes media to the authenticated user', function () {

    $owner = $this->adminUser();
    $intruder = $this->adminUser();

    $media = Media::factory()->create([
        MediaSchema::USER_ID => $owner->id,
        MediaSchema::FOLDER_ID => null,
    ]);

    $this->actingAs($intruder);

    $list = $this->getJson($this->baseUrl('/media?filter[folder_id]=null'));
    $list->assertOk();
    expect(collect($list->json('data'))->pluck('id'))->not->toContain($media->id);

    $this->patchJson($this->baseUrl('/media/'.$media->id), [MediaSchema::NAME => 'hijacked'])
        ->assertStatus(404);

    $this->deleteJson($this->baseUrl('/media/'.$media->id))->assertStatus(404);

    expect($media->fresh())->not->toBeNull();
});

it('prevents moving media into a folder owned by another user', function () {

    $owner = $this->adminUser();
    $intruder = $this->adminUser();

    $ownerFolder = Folder::factory()->create([
        FolderSchema::USER_ID => $owner->id,
        FolderSchema::PARENT_ID => null,
    ]);

    $intruderMedia = Media::factory()->create([
        MediaSchema::USER_ID => $intruder->id,
        MediaSchema::FOLDER_ID => null,
    ]);

    $this->actingAs($intruder);

    $response = $this->patchJson($this->baseUrl('/media/'.$intruderMedia->id), [
        MediaSchema::FOLDER_ID => $ownerFolder->id,
    ]);

    $response->assertStatus(404);
    expect($intruderMedia->fresh()->folder_id)->toBeNull();
});

it('uploads media to root without a folder_id', function () {

    Storage::fake('public');

    $user = $this->adminUser();
    $this->actingAs($user);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post($this->baseUrl('/media'), [
        MediaSchema::REQ_FILE => UploadedFile::fake()->image('photo.jpg'),
    ]);

    $response->assertCreated();
    expect($response->json('data.folder_id'))->toBeNull();

    $this->assertDatabaseHas(MediaSchema::TABLE, [
        MediaSchema::USER_ID => $user->id,
        MediaSchema::FOLDER_ID => null,
    ]);
});

it('uploads media into an owned folder', function () {

    Storage::fake('public');

    $user = $this->adminUser();
    $this->actingAs($user);

    $folder = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => null,
    ]);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post($this->baseUrl('/media'), [
        MediaSchema::REQ_FILE => UploadedFile::fake()->image('photo.jpg'),
        MediaSchema::FOLDER_ID => $folder->id,
    ]);

    $response->assertCreated();
    expect($response->json('data.folder_id'))->toBe($folder->id);
});

it('prevents uploading media into a folder owned by another user', function () {

    Storage::fake('public');

    $owner = $this->adminUser();
    $intruder = $this->adminUser();

    $ownerFolder = Folder::factory()->create([
        FolderSchema::USER_ID => $owner->id,
        FolderSchema::PARENT_ID => null,
    ]);

    $this->actingAs($intruder);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post($this->baseUrl('/media'), [
        MediaSchema::REQ_FILE => UploadedFile::fake()->image('photo.jpg'),
        MediaSchema::FOLDER_ID => $ownerFolder->id,
    ]);

    $response->assertStatus(404);
    $this->assertDatabaseMissing(MediaSchema::TABLE, [
        MediaSchema::USER_ID => $intruder->id,
    ]);
});
