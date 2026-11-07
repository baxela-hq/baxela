<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;
use Modules\Support\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports tickets and messages (export-only)', function () {
    $this->actingAs($this->superAdminUser());

    $ticket = Ticket::query()->create([
        'user_id' => 7, 'order_code' => 'AGVPHVHZ', 'subject' => 'Where is my order?',
        'status' => 'open', 'last_message_at' => now(),
    ]);
    TicketMessage::query()->create([
        'ticket_id' => $ticket->id, 'user_id' => 7, 'sender' => 'customer', 'body' => 'Hello?',
    ]);

    $file = $this->exportedSupportDataFile();

    expect($file['module'])->toBe('support')
        ->and(array_column($file['sections'], 'entity'))->toBe(['tickets', 'ticket-messages'])
        ->and($file['sections'][0]['rows'][0]['payload'][TicketSchema::SUBJECT])->toBe('Where is my order?')
        ->and($file['sections'][0]['rows'][0]['payload']['status'])->toBe('open')
        ->and($file['sections'][1]['rows'][0]['payload'][TicketMessageSchema::SENDER])->toBe('customer');
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
});
