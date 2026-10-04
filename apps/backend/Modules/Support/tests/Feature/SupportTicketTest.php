<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Order\Gateways\DTOs\GetOrderOutput;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\Schemas\Ticket\TicketStatusEnum;
use Modules\Support\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function ticketPayload(array $overrides = []): array
{
    return array_merge([
        'subject' => 'Question about my order',
        'body' => 'Where is my order?',
        'order_code' => null,
    ], $overrides);
}

function ownOrderGateway(User $user, string $code, bool $owns): void
{
    $gateway = Mockery::mock(OrderGatewayInterface::class);
    $gateway->shouldReceive('getOrder')
        ->with($code, (string) $user->id)
        ->andReturn($owns ? Mockery::mock(GetOrderOutput::class) : null);

    app()->instance(OrderGatewayInterface::class, $gateway);
}

it('lets a customer create a ticket with a first message', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson($this->baseUrl('/user/tickets'), ticketPayload())
        ->assertCreated();

    $ticket = Ticket::query()->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->user_id)->toBe($user->id)
        ->and($ticket->status->value)->toBe('open')
        ->and(TicketMessage::count())->toBe(1)
        ->and(TicketMessage::query()->first()->sender->value)->toBe('customer');
});

it('accepts an order code owned by the customer', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    ownOrderGateway($user, 'ORD-123', true);

    $this->postJson($this->baseUrl('/user/tickets'), ticketPayload(['order_code' => 'ORD-123']))
        ->assertCreated();

    expect(Ticket::query()->first()->order_code)->toBe('ORD-123');
});

it('rejects an order code the customer does not own', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    ownOrderGateway($user, 'ORD-999', false);

    $this->postJson($this->baseUrl('/user/tickets'), ticketPayload(['order_code' => 'ORD-999']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'support.ticket.invalid_order');

    expect(Ticket::count())->toBe(0);
});

it('rejects an invalid ticket payload', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson($this->baseUrl('/user/tickets'), ticketPayload(['subject' => '']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'http.422');

    expect(Ticket::count())->toBe(0);
});

it('only lists and shows the customer own tickets', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $own = Ticket::factory()->create(['user_id' => $alice->id]);
    Ticket::factory()->create(['user_id' => $bob->id]);

    $this->actingAs($alice);

    $this->getJson($this->baseUrl('/user/tickets'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $own->id);

    $this->getJson($this->baseUrl('/user/tickets'))
        ->assertOk();

    $others = Ticket::query()->where('user_id', $bob->id)->first();

    $this->getJson($this->baseUrl('/user/tickets/'.$others->id))
        ->assertNotFound();
});

it('reopens a closed ticket when the customer replies', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'user_id' => $user->id,
        'status' => TicketStatusEnum::CLOSED,
    ]);

    $this->actingAs($user);

    $this->postJson($this->baseUrl('/user/tickets/'.$ticket->id.'/messages'), ['body' => 'It happened again'])
        ->assertCreated();

    $ticket->refresh();

    expect($ticket->status->value)->toBe('open')
        ->and($ticket->last_message_at)->not->toBeNull()
        ->and(TicketMessage::count())->toBe(1)
        ->and(TicketMessage::query()->first()->sender->value)->toBe('customer');
});

it('lets the customer close and reopen their own ticket but not mark it answered', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $this->patchJson($this->baseUrl('/user/tickets/'.$ticket->id.'/status'), ['status' => 'closed'])
        ->assertOk();

    expect($ticket->refresh()->status->value)->toBe('closed');

    $this->patchJson($this->baseUrl('/user/tickets/'.$ticket->id.'/status'), ['status' => 'open'])
        ->assertOk();

    expect($ticket->refresh()->status->value)->toBe('open');

    $this->patchJson($this->baseUrl('/user/tickets/'.$ticket->id.'/status'), ['status' => 'answered'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'http.422');
});

it('lists, shows, replies to, updates and deletes tickets as an admin', function () {
    $this->actingAs($this->superAdminUser());

    $ticket = Ticket::factory()->create(['status' => TicketStatusEnum::OPEN]);
    TicketMessage::factory()->create([
        'ticket_id' => $ticket->id,
        'sender' => 'customer',
        'body' => 'Where is my order?',
    ]);

    $this->getJson($this->baseUrl('/admin/tickets'))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson($this->baseUrl('/admin/tickets/'.$ticket->id))
        ->assertOk()
        ->assertJsonPath('data.id', $ticket->id)
        ->assertJsonCount(1, 'data.messages');

    $this->postJson($this->baseUrl('/admin/tickets/'.$ticket->id.'/messages'), ['body' => 'We are checking it now'])
        ->assertCreated();

    expect($ticket->refresh()->status->value)->toBe('answered')
        ->and(TicketMessage::count())->toBe(2);

    $this->patchJson($this->baseUrl('/admin/tickets/'.$ticket->id.'/status'), ['status' => 'closed'])
        ->assertOk();

    expect($ticket->refresh()->status->value)->toBe('closed');

    $this->deleteJson($this->baseUrl('/admin/tickets/'.$ticket->id))
        ->assertNoContent();

    expect(Ticket::count())->toBe(0)
        ->and(TicketMessage::count())->toBe(0);
});

it('denies admin ticket routes to regular customers', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson($this->baseUrl('/admin/tickets'))
        ->assertForbidden();
});
