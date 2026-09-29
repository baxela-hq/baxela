<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\Gateways\Media\MediaGatewayInterface;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Media\MediaSchema;
use Modules\Media\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('resolves a media record by id through the gateway', function () {

    $media = Media::factory()->create([
        MediaSchema::DISK => 'public',
        MediaSchema::PATH => 'catalog/imports/products.csv',
        MediaSchema::NAME => 'products',
        MediaSchema::EXTENSION => 'csv',
    ]);

    $dto = app(MediaGatewayInterface::class)->findById($media->id);

    expect($dto)->not->toBeNull()
        ->and($dto->id)->toBe($media->id)
        ->and($dto->disk)->toBe('public')
        ->and($dto->path)->toBe('catalog/imports/products.csv')
        ->and($dto->name)->toBe('products')
        ->and($dto->extension)->toBe('csv');
});

it('returns null for an unknown media id', function () {

    expect(app(MediaGatewayInterface::class)->findById(999))->toBeNull();
});
