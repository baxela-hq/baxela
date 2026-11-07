<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Webhooks\Schemas\WebhookEndpoint\WebhookEndpointSchema;
use Modules\Webhooks\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('denies a guest with 401', function () {
    $this->getJson($this->baseUrl('/admin/endpoints'))->assertStatus(401);
});

it('creates an endpoint, validates SSRF at the boundary, shows the secret once', function () {
    $this->actingAs($this->superAdminUser());

    $created = $this->postJson($this->baseUrl('/admin/endpoints'), [
        'name' => 'ERP',
        'url' => 'https://erp.example.test/hook',
        'events' => ['order.paid', 'refund.processed'],
        'is_active' => true,
    ])->assertCreated();

    // Plaintext secret shown once, and NOT part of the resource payload.
    expect($created->json('secret'))->toBeString()->not->toBeEmpty()
        ->and($created->json('data.secret'))->toBeNull()
        ->and($created->json('data.url'))->toBe('https://erp.example.test/hook');

    // Subsequent reads never expose it.
    $shown = $this->getJson($this->baseUrl('/admin/endpoints/'.$created->json('data.id')))
        ->assertOk();
    expect($shown->json('data'))->not->toHaveKey('secret');
});

it('rejects a private URL at create time', function () {
    $this->actingAs($this->superAdminUser());

    // Local host (no real DNS in the test env resolves this publicly).
    $this->postJson($this->baseUrl('/admin/endpoints'), [
        'name' => 'internal',
        'url' => 'https://127.0.0.1/hook',
        'events' => ['order.paid'],
        'is_active' => true,
    ])->assertStatus(400)
        ->assertJsonPath('code', 'webhooks.url.invalid');
});

it('rejects unknown event types', function () {
    $this->actingAs($this->superAdminUser());

    $this->postJson($this->baseUrl('/admin/endpoints'), [
        'name' => 'bad',
        'url' => 'https://erp.example.test/hook',
        'events' => ['not.a.type'],
        'is_active' => true,
    ])->assertStatus(422);
});

it('archives instead of deleting', function () {
    [$endpoint] = $this->webhookEndpoint();
    $this->actingAs($this->superAdminUser());

    $this->deleteJson($this->baseUrl('/admin/endpoints/'.$endpoint->getKey()))->assertNoContent();

    expect($endpoint->refresh()->{WebhookEndpointSchema::IS_ARCHIVED})->toBeTrue()
        ->and($endpoint->refresh()->{WebhookEndpointSchema::SECRET})->not->toBeNull();
});

it('regenerates the secret and invalidates the old one', function () {
    [$endpoint, $oldSecret] = $this->webhookEndpoint();
    $this->actingAs($this->superAdminUser());

    $regenerated = $this->postJson($this->baseUrl('/admin/endpoints/'.$endpoint->getKey().'/regenerate-secret'))
        ->assertOk();

    $newSecret = $regenerated->json('secret');

    expect($newSecret)->toBeString()
        ->and($newSecret)->not->toBe($oldSecret)
        // Encrypted at rest: the stored value is never the plaintext.
        ->and($endpoint->refresh()->getRawOriginal(WebhookEndpointSchema::SECRET))->not->toBe($newSecret);
});
