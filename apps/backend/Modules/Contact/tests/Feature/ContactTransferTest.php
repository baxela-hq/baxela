<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Contact\Models\ContactMessage;
use Modules\Contact\Models\NewsletterSubscriber;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberStatusEnum;
use Modules\Contact\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports subscribers and messages with messages export-only', function () {
    $this->seedTransferContact();
    $this->actingAs($this->superAdminUser());

    $file = $this->exportedContactFile();

    expect($file['module'])->toBe('contact')
        ->and(array_column($file['sections'], 'entity'))->toBe(['newsletter-subscribers', 'contact-messages'])
        ->and($file['sections'][0]['rows'][0]['payload']['email'])->toBe('fan@example.com')
        ->and($file['sections'][1]['rows'][0]['payload']['subject'])->toBe('Question')
        ->and($file['sections'][1]['rows'][0]['payload']['status'])->toBe('unread');

    // Messages are correspondence history — the capabilities matrix
    // marks them as export-only.
    $capabilities = collect(
        $this->getJson($this->transferUrl('/admin/capabilities'))->assertOk()->json('data')
    )->firstWhere('module', 'contact');
    $messages = collect($capabilities['sections'])->firstWhere('key', 'contact-messages');
    expect($messages['exportable'])->toBeTrue()
        ->and($messages['importable'])->toBeFalse();
});

it('imports the newsletter list: creates, updates and re-subscribes', function () {
    $this->seedTransferContact();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedContactFile();

    // Fresh store: the subscriber list is empty, messages stay behind.
    NewsletterSubscriber::query()->delete();
    ContactMessage::query()->delete();

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('contact.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.created_count'))->toBe(1)
        ->and($response->json('data.skipped_count'))->toBe(1)
        ->and(NewsletterSubscriber::query()->count())->toBe(1)
        ->and(ContactMessage::query()->count())->toBe(0)
        ->and(NewsletterSubscriber::query()->first()->email)->toBe('fan@example.com');

    // An unsubscribed row re-imported flips the status (subscribe flow
    // semantics), and a skipped strategy leaves existing rows alone.
    NewsletterSubscriber::query()->update([
        'status' => NewsletterSubscriberStatusEnum::UNSUBSCRIBED->value,
    ]);

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('contact.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.updated_count'))->toBe(1)
        ->and(NewsletterSubscriber::query()->first()->status)->toBe(NewsletterSubscriberStatusEnum::SUBSCRIBED);
});

it('skips existing subscribers with the skip strategy', function () {
    $this->seedTransferContact();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedContactFile();
    NewsletterSubscriber::query()->delete();
    NewsletterSubscriber::query()->create([
        'email' => 'fan@example.com',
        'locale' => 'de',
        'status' => 'subscribed',
    ]);

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('contact.json', json_encode($payload)),
        'on_duplicate' => 'skip',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.skipped_count'))->toBe(2)
        ->and(NewsletterSubscriber::query()->first()->locale)->toBe('de');
});

it('fails subscriber rows with an invalid email', function () {
    $this->actingAs($this->superAdminUser());

    $payload = [
        'format' => 'baxela.module-export',
        'module' => 'contact',
        'version' => 1,
        'sections' => [
            ['entity' => 'newsletter-subscribers', 'rows' => [
                ['source_id' => 1, 'payload' => ['email' => 'not-an-email', 'locale' => 'en', 'status' => 'subscribed']],
            ]],
            ['entity' => 'contact-messages', 'rows' => []],
        ],
    ];

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('contact.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.failed_count'))->toBe(1)
        ->and($response->json('data.errors.0'))->toContain('newsletter-subscribers[1]')
        ->and(NewsletterSubscriber::query()->count())->toBe(0);
});

it('dry run validates without writing', function () {
    $this->seedTransferContact();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedContactFile();
    NewsletterSubscriber::query()->delete();

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('contact.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => true,
    ]);

    $response->assertOk();
    expect($response->json('data.created_count'))->toBe(1)
        ->and(NewsletterSubscriber::query()->count())->toBe(0);
});
