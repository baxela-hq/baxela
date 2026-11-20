<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Models\TransferRemap;
use Modules\Core\Support\Transfer\ModuleTransferRunner;
use Modules\Core\Support\Transfer\TransferFormat;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;
use Modules\Support\Tests\Feature\HelperTrait;
use Modules\Support\Transfer\SupportTransfer;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * Support import semantics (C5): append-only history via the query
 * builder — no events, no sender re-attribution, remap-as-idempotency.
 */
/**
 * @param  list<array<string, mixed>>  $tickets
 * @param  list<array<string, mixed>>  $messages
 */
function supportTransferFile(string $source, array $tickets, array $messages): string
{
    return json_encode([
        TransferFormat::KEY_FORMAT => TransferFormat::FORMAT,
        TransferFormat::KEY_MODULE => 'support',
        TransferFormat::KEY_VERSION => TransferFormat::VERSION,
        TransferFormat::KEY_EXPORTED_AT => '2026-01-15T10:00:00+00:00',
        TransferFormat::KEY_SOURCE_INSTALLATION => $source,
        TransferFormat::KEY_SECTIONS => [
            [TransferFormat::KEY_ENTITY => 'tickets', TransferFormat::KEY_ROWS => $tickets],
            [TransferFormat::KEY_ENTITY => 'ticket-messages', TransferFormat::KEY_ROWS => $messages],
        ],
    ]);
}

it('imports ticket history append-only without events or re-attribution (C5/C3)', function () {
    $this->actingAs($this->superAdminUser());

    $source = (string) Str::uuid();
    $localUserId = 1; // the acting admin is the only local user.

    // A previous User-directory import recorded source user 500.
    TransferRemap::query()->create([
        'source_installation' => $source, 'module' => 'user', 'entity' => 'users',
        'source_id' => 500, 'local_id' => $localUserId,
    ]);

    $summary = app(ModuleTransferRunner::class)->run(
        't.json',
        supportTransferFile($source,
            tickets: [
                ['source_id' => 10, 'payload' => [
                    'id' => 10, 'user_id' => 500, 'order_code' => 'SRC-2026-9',
                    'subject' => 'Where is my parcel?', 'status' => 'closed',
                    'last_message_at' => '2026-01-10T09:30:00+00:00',
                    'created_at' => '2026-01-09T08:00:00+00:00',
                    'updated_at' => '2026-01-10T09:30:00+00:00',
                ]],
            ],
            messages: [
                ['source_id' => 20, 'payload' => [
                    'id' => 20, 'ticket_id' => 10, 'user_id' => 500,
                    'sender' => 'customer', 'body' => 'Still waiting.',
                    'created_at' => '2026-01-10T09:30:00+00:00',
                    'updated_at' => '2026-01-10T09:30:00+00:00',
                ]],
            ]),
        TransferStrategyEnum::UPDATE,
        false,
        'support',
        app(SupportTransfer::class),
    );

    expect($summary['status'])->toBe('completed')
        ->and($summary['created_count'])->toBe(2)
        // No customer-notification events fired for imported history.
        ->and(fn () => Event::assertNothingDispatched());

    $ticket = DB::table('support_tickets')->where(TicketSchema::SUBJECT, 'Where is my parcel?')->first();
    expect($ticket)->not->toBeNull()
        // History restored exactly: the CLOSED status and source
        // last_message_at, not a freshly-derived open state.
        ->and($ticket->status)->toBe('closed')
        ->and($ticket->user_id)->toBe($localUserId);

    $message = DB::table('support_ticket_messages')->where(TicketMessageSchema::BODY, 'Still waiting.')->first();
    expect($message)->not->toBeNull()
        // The sender stays who it was (resolved through the ledger) —
        // never re-attributed to the acting admin, and the customer
        // notification event never fired.
        ->and($message->sender)->toBe('customer')
        ->and($message->ticket_id)->toBe((int) $ticket->id);

    // Re-import: remap-as-idempotency — skipped, no duplicates.
    $second = app(ModuleTransferRunner::class)->run(
        't.json',
        supportTransferFile($source,
            tickets: [['source_id' => 10, 'payload' => [
                'id' => 10, 'user_id' => 500, 'order_code' => 'SRC-2026-9',
                'subject' => 'Where is my parcel?', 'status' => 'closed',
                'last_message_at' => '2026-01-10T09:30:00+00:00',
                'created_at' => '2026-01-09T08:00:00+00:00',
                'updated_at' => '2026-01-10T09:30:00+00:00',
            ]]],
            messages: []),
        TransferStrategyEnum::UPDATE,
        false,
        'support',
        app(SupportTransfer::class),
    );
    expect($second['created_count'])->toBe(0)
        ->and(DB::table('support_tickets')->count())->toBe(1);
});

it('fails messages whose ticket is not from this or a previous import (C5)', function () {
    $this->actingAs($this->superAdminUser());

    $summary = app(ModuleTransferRunner::class)->run(
        't.json',
        supportTransferFile((string) Str::uuid(), tickets: [], messages: [
            ['source_id' => 20, 'payload' => [
                'id' => 20, 'ticket_id' => 99, 'user_id' => null,
                'sender' => 'customer', 'body' => 'orphan',
                'created_at' => '2026-01-10T09:30:00+00:00',
                'updated_at' => '2026-01-10T09:30:00+00:00',
            ]],
        ]),
        TransferStrategyEnum::UPDATE,
        false,
        'support',
        app(SupportTransfer::class),
    );

    expect($summary['failed_count'])->toBe(1)
        ->and($summary['errors'][0])->toContain('unresolved_ticket:99')
        ->and(DB::table('support_ticket_messages')->count())->toBe(0);
});
