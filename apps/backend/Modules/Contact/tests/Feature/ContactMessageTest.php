<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contact\Models\ContactMessage;
use Modules\Contact\Schemas\ContactMessage\ContactMessageStatusEnum;
use Modules\Contact\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function messagePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => null,
        'subject' => 'Question about my order',
        'content' => 'Where is my order?',
    ], $overrides);
}

it('accepts a public contact submission as unread', function () {
    $response = $this->postJson($this->baseUrl('/public/messages'), messagePayload())
        ->assertCreated();

    $message = ContactMessage::query()->find($response->json('data.id'));

    expect($message)->not->toBeNull()
        ->and($message->subject)->toBe('Question about my order')
        ->and($message->status->value)->toBe('unread');
});

it('rejects an invalid submission', function () {
    $this->postJson($this->baseUrl('/public/messages'), messagePayload(['email' => 'not-an-email']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'http.422');

    expect(ContactMessage::count())->toBe(0);
});

it('lists, shows, updates status and deletes messages as an admin', function () {
    $this->actingAs($this->superAdminUser());

    $message = ContactMessage::factory()->create(['status' => ContactMessageStatusEnum::UNREAD]);

    $this->getJson($this->baseUrl('/admin/messages'))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson($this->baseUrl('/admin/messages/'.$message->id))
        ->assertOk()
        ->assertJsonPath('data.id', $message->id);

    $this->patchJson($this->baseUrl('/admin/messages/'.$message->id.'/status'), ['status' => 'replied'])
        ->assertOk();

    expect($message->refresh()->status->value)->toBe('replied');

    $this->deleteJson($this->baseUrl('/admin/messages/'.$message->id))
        ->assertNoContent();

    expect(ContactMessage::count())->toBe(0);
});
