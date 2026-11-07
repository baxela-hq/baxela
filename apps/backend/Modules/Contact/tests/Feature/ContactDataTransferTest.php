<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contact\Models\ContactMessage;
use Modules\Contact\Models\NewsletterSubscriber;
use Modules\Contact\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports contact data through the module endpoint', function () {
    $this->seedTransferContact();
    $this->actingAs($this->superAdminUser());

    $file = $this->exportedContactDataFile();

    expect($file['module'])->toBe('contact')
        ->and(array_column($file['sections'], 'entity'))->toBe(['newsletter-subscribers', 'contact-messages'])
        ->and($file['sections'][0]['rows'][0]['payload']['email'])->toBe('fan@example.com')
        ->and($file['sections'][1]['rows'][0]['payload']['status'])->toBe('unread');
});

it('round-trips the newsletter list via the module endpoint', function () {
    $this->seedTransferContact();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedContactDataFile();

    NewsletterSubscriber::query()->delete();
    ContactMessage::query()->delete();

    $response = $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.created_count'))->toBe(1)
        ->and($response->json('data.skipped_count'))->toBe(1)
        ->and($response->json('data.warnings.0'))->toBe('not_importable:contact-messages')
        ->and(NewsletterSubscriber::query()->count())->toBe(1)
        ->and(ContactMessage::query()->count())->toBe(0);
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
    $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile('{}'),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertStatus(401);
});
