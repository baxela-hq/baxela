<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Gateways\Media\MediaGatewayInterface;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Media\MediaSchema;
use Modules\Media\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function seedImageSource(): string
{
    return __DIR__.'/../../../Catalog/database/seeders/media/brands/adidas.png';
}

it('uses the authenticated user as the media actor', function () {
    Storage::fake('public');

    $actor = User::factory()->create();
    $this->actingAs($actor);

    app(MediaGatewayInterface::class)->upsertLocal(seedImageSource(), 'tests/actor.png');

    $media = Media::query()->where(MediaSchema::PATH, 'tests/actor.png')->firstOrFail();

    expect($media->{MediaSchema::USER_ID})->toBe($actor->id);
});

it('accepts an explicit actor for unattended calls', function () {
    Storage::fake('public');

    $actor = User::factory()->create();

    app(MediaGatewayInterface::class)->upsertLocal(seedImageSource(), 'tests/explicit.png', null, $actor->id);

    $media = Media::query()->where(MediaSchema::PATH, 'tests/explicit.png')->firstOrFail();

    expect($media->{MediaSchema::USER_ID})->toBe($actor->id);
});

it('refuses an unattended upsert with no actor', function () {
    Storage::fake('public');

    app(MediaGatewayInterface::class)->upsertLocal(seedImageSource(), 'tests/no-actor.png');
})->throws(RuntimeException::class);
