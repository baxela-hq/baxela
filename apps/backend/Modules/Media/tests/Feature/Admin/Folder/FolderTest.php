<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Media\Models\Folder;
use Modules\Media\Schemas\Folder\FolderSchema;
use Modules\Media\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('lists root folders via filter[parent_id]=null', function () {

    $user = $this->adminUser();
    $this->actingAs($user);

    $rootA = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => null,
    ]);
    $rootB = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => null,
    ]);
    $child = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => $rootA->id,
    ]);

    $response = $this->getJson($this->baseUrl('/folders?filter[parent_id]=null'));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toHaveCount(2)
        ->and($ids)->toContain($rootA->id, $rootB->id)
        ->and($ids)->not->toContain($child->id);
});

it('lists child folders via filter[parent_id]={id}', function () {

    $user = $this->adminUser();
    $this->actingAs($user);

    $parent = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => null,
    ]);
    $child = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => $parent->id,
    ]);
    $otherRoot = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => null,
    ]);

    $response = $this->getJson($this->baseUrl('/folders?filter[parent_id]='.$parent->id));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toHaveCount(1)
        ->and($ids)->toContain($child->id)
        ->and($ids)->not->toContain($parent->id, $otherRoot->id);
});

it('renames a folder via PATCH', function () {

    $user = $this->adminUser();
    $this->actingAs($user);

    $folder = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => null,
        FolderSchema::NAME => 'Old Name',
    ]);

    $response = $this->patchJson($this->baseUrl('/folders/'.$folder->id), [
        FolderSchema::NAME => 'New Name',
    ]);

    $response->assertOk();
    expect($response->json('data.name'))->toBe('New Name');
    expect($folder->fresh()->name)->toBe('New Name');
});

it('moves a folder into another folder via PATCH', function () {

    $user = $this->adminUser();
    $this->actingAs($user);

    $parent = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => null,
    ]);
    $folder = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => null,
    ]);

    $response = $this->patchJson($this->baseUrl('/folders/'.$folder->id), [
        FolderSchema::PARENT_ID => $parent->id,
    ]);

    $response->assertOk();
    expect($response->json('data.parent_id'))->toBe($parent->id);
    expect($folder->fresh()->parent_id)->toBe($parent->id);
});

it('moves a folder to root via PATCH with parent_id=null', function () {

    $user = $this->adminUser();
    $this->actingAs($user);

    $parent = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => null,
    ]);
    $folder = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => $parent->id,
    ]);

    $response = $this->patchJson($this->baseUrl('/folders/'.$folder->id), [
        FolderSchema::PARENT_ID => null,
    ]);

    $response->assertOk();
    expect($response->json('data.parent_id'))->toBeNull();
    expect($folder->fresh()->parent_id)->toBeNull();
});

it('prevents moving a folder into itself', function () {

    $user = $this->adminUser();
    $this->actingAs($user);

    $folder = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => null,
    ]);

    $response = $this->patchJson($this->baseUrl('/folders/'.$folder->id), [
        FolderSchema::PARENT_ID => $folder->id,
    ]);

    $response->assertStatus(422);
    expect($response->json('code'))->toBe('media.folder.circular_move');
});

it('prevents moving a folder into one of its descendants', function () {

    $user = $this->adminUser();
    $this->actingAs($user);

    $root = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => null,
    ]);
    $child = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => $root->id,
    ]);
    $grandchild = Folder::factory()->create([
        FolderSchema::USER_ID => $user->id,
        FolderSchema::PARENT_ID => $child->id,
    ]);

    $response = $this->patchJson($this->baseUrl('/folders/'.$root->id), [
        FolderSchema::PARENT_ID => $grandchild->id,
    ]);

    $response->assertStatus(422);
    expect($response->json('code'))->toBe('media.folder.circular_move');
    expect($root->fresh()->parent_id)->toBeNull();
});

it('scopes folders to the authenticated user', function () {

    $owner = $this->adminUser();
    $intruder = $this->adminUser();

    $folder = Folder::factory()->create([
        FolderSchema::USER_ID => $owner->id,
        FolderSchema::PARENT_ID => null,
        FolderSchema::NAME => 'Owner Folder',
    ]);

    $this->actingAs($intruder);

    $list = $this->getJson($this->baseUrl('/folders?filter[parent_id]=null'));
    $list->assertOk();
    expect(collect($list->json('data'))->pluck('id'))->not->toContain($folder->id);

    $this->patchJson($this->baseUrl('/folders/'.$folder->id), [FolderSchema::NAME => 'Hijacked'])
        ->assertStatus(404);

    $this->deleteJson($this->baseUrl('/folders/'.$folder->id))->assertStatus(404);

    expect($folder->fresh()->name)->toBe('Owner Folder');
});

it('creates a folder with name only', function () {

    $user = $this->adminUser();
    $this->actingAs($user);

    $response = $this->postJson($this->baseUrl('/folders'), [
        FolderSchema::NAME => 'My Folder',
    ]);

    $response->assertCreated();
    expect($response->json('data.name'))->toBe('My Folder');

    $this->assertDatabaseHas(FolderSchema::TABLE, [
        FolderSchema::USER_ID => $user->id,
        FolderSchema::NAME => 'My Folder',
        FolderSchema::PARENT_ID => null,
    ]);
});

it('prevents creating a folder under another user folder', function () {

    $owner = $this->adminUser();
    $intruder = $this->adminUser();

    $ownerFolder = Folder::factory()->create([
        FolderSchema::USER_ID => $owner->id,
        FolderSchema::PARENT_ID => null,
    ]);

    $this->actingAs($intruder);

    $response = $this->postJson($this->baseUrl('/folders'), [
        FolderSchema::NAME => 'Intruder Child',
        FolderSchema::PARENT_ID => $ownerFolder->id,
    ]);

    $response->assertStatus(404);
});
