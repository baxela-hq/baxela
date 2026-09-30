<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Modules\Contact\Models\NewsletterSubscriber;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberSchema;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberStatusEnum;
use Modules\Contact\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('accepts a public newsletter subscription', function () {
    $response = $this->postJson($this->baseUrl('/public/newsletter-subscribers'), [
        'email' => 'reader@example.com',
    ])->assertCreated();

    $subscriber = NewsletterSubscriber::query()->find($response->json('data.id'));

    expect($subscriber)->not->toBeNull()
        ->and($subscriber->{NewsletterSubscriberSchema::EMAIL})->toBe('reader@example.com')
        ->and($subscriber->{NewsletterSubscriberSchema::STATUS}->value)->toBe('subscribed');
});

it('rejects an invalid email', function () {
    $this->postJson($this->baseUrl('/public/newsletter-subscribers'), [
        'email' => 'not-an-email',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'http.422');

    expect(NewsletterSubscriber::count())->toBe(0);
});

it('re-subscribes a known email instead of failing on the unique constraint', function () {
    $unsubscribed = NewsletterSubscriber::factory()->create([
        NewsletterSubscriberSchema::EMAIL => 'back@example.com',
        NewsletterSubscriberSchema::STATUS => NewsletterSubscriberStatusEnum::UNSUBSCRIBED->value,
    ]);

    $this->postJson($this->baseUrl('/public/newsletter-subscribers'), [
        'email' => 'back@example.com',
    ])->assertCreated();

    expect(NewsletterSubscriber::count())->toBe(1)
        ->and($unsubscribed->fresh()->{NewsletterSubscriberSchema::STATUS}->value)->toBe('subscribed');
});

it('rate-limits newsletter submissions', function () {
    Config::set('contact.rate_limit.subscribe', 2);

    $this->postJson($this->baseUrl('/public/newsletter-subscribers'), ['email' => 'one@example.com']);
    $this->postJson($this->baseUrl('/public/newsletter-subscribers'), ['email' => 'two@example.com']);
    $this->postJson($this->baseUrl('/public/newsletter-subscribers'), ['email' => 'three@example.com'])
        ->assertStatus(429);
});

it('lists, filters and sorts subscribers for admins', function () {
    $this->actingAs($this->superAdminUser());

    $subscribed = NewsletterSubscriber::factory()->create([
        NewsletterSubscriberSchema::EMAIL => 'active@example.com',
    ]);
    NewsletterSubscriber::factory()->create([
        NewsletterSubscriberSchema::EMAIL => 'gone@example.com',
        NewsletterSubscriberSchema::STATUS => NewsletterSubscriberStatusEnum::UNSUBSCRIBED->value,
    ]);

    $this->getJson($this->baseUrl('/admin/newsletter-subscribers'))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $ids = collect(
        $this->getJson($this->baseUrl('/admin/newsletter-subscribers?filter[status]=subscribed'))->json('data')
    )->pluck('id');

    expect($ids)->toContain($subscribed->id)->toHaveCount(1);
});

it('updates a subscriber status and deletes a subscriber', function () {
    $this->actingAs($this->superAdminUser());

    $subscriber = NewsletterSubscriber::factory()->create();

    $this->patchJson($this->baseUrl('/admin/newsletter-subscribers/'.$subscriber->id.'/status'), [
        NewsletterSubscriberSchema::STATUS => NewsletterSubscriberStatusEnum::UNSUBSCRIBED->value,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', NewsletterSubscriberStatusEnum::UNSUBSCRIBED->value);

    $this->deleteJson($this->baseUrl('/admin/newsletter-subscribers/'.$subscriber->id))
        ->assertNoContent();

    expect(NewsletterSubscriber::count())->toBe(0);
});
