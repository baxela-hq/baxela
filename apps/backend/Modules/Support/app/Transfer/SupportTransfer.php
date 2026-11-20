<?php

namespace Modules\Support\Transfer;

use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferRemapInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
use Modules\Core\Support\Transfer\TransferImportTransaction;
use Modules\Core\Support\Transfer\TransferRunContext;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\Schemas\Module;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;
use Throwable;

/**
 * Support module's implementation of the data-transfer standard (C5):
 * tickets and their conversations are per-user HISTORY, imported as
 * immutable append-only rows through the query builder — never through
 * CreateTicketMessageAction, which would stamp sender=admin /
 * Auth::id() and fire a customer notification per message. Status and
 * last_message_at are restored exactly as exported, and no events fire
 * on import.
 *
 * Identity: tickets/messages are keyless, so the persisted remap is the
 * idempotency key (C5) — no remap → create + record; existing remap →
 * skip, never update (append-only); a dangling remap recreates and
 * overwrites (warned). User refs resolve through the ledger and null out
 * with a warning when unresolvable (the nullable sender/owner columns).
 */
class SupportTransfer implements ModuleTransferInterface
{
    public const string SECTION_TICKETS = 'tickets';

    public const string SECTION_TICKET_MESSAGES = 'ticket-messages';

    public function __construct(
        protected TransferRemapInterface $remaps,
        protected TransferImportTransaction $units,
    ) {}

    public function sections(): array
    {
        return [
            TransferSection::full(self::SECTION_TICKETS),
            TransferSection::full(self::SECTION_TICKET_MESSAGES),
        ];
    }

    public function exportRows(string $section): array
    {
        return match ($section) {
            self::SECTION_TICKETS => $this->exportRowsOf(Ticket::query()->orderBy(TicketSchema::ID)),
            self::SECTION_TICKET_MESSAGES => $this->exportRowsOf(TicketMessage::query()->orderBy(TicketMessageSchema::ID)),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        return match ($section) {
            self::SECTION_TICKETS => $this->importTickets($rows, $options),
            self::SECTION_TICKET_MESSAGES => $this->importMessages($rows, $options),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importTickets(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_TICKETS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            if (trim((string) ($payload[TicketSchema::SUBJECT] ?? '')) === '') {
                $result->recordFailed($label.': missing_subject');

                continue;
            }

            try {
                // Remap-as-idempotency-key (C5): keyless immutable rows.
                $remappedId = $this->remaps->resolve($options->sourceInstallation, Module::NAME_LOWER, 'tickets', (int) $sourceId);
                if ($remappedId !== null && $this->ticketExists($remappedId)) {
                    if ($options->dryRun) {
                        $this->dryMatched($result, $options);

                        continue;
                    }

                    // Append-only: skip, never update.
                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();
                    } else {
                        $result->recordUpdated();
                    }

                    continue;
                }

                $userId = $this->resolveUserId($payload[TicketSchema::USER_ID] ?? null, $options);
                if ($payload[TicketSchema::USER_ID] !== null && $userId === null) {
                    $result->warn($label.': loose_ref_unresolved:user.users:'.($payload[TicketSchema::USER_ID] ?? ''));
                }

                if ($options->dryRun) {
                    $result->recordCreated();

                    continue;
                }

                $values = $payload;
                unset($values['id']);
                $values[TicketSchema::USER_ID] = $userId;

                $this->units->run(function (TransferRunContext $context) use ($values, $sourceId): void {
                    $localId = (int) DB::table(TicketSchema::TABLE)->insertGetId($values);
                    $this->remaps->record($context->sourceInstallation, Module::NAME_LOWER, 'tickets', (int) $sourceId, $localId);
                });

                $result->recordCreated();
            } catch (Throwable $e) {
                TransferLockSessionLostException::rethrow($e); // session loss is fatal (C7), never a row failure
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * Messages are append-only conversation history; ticket refs resolve
     * through the ledger (tickets ran first), sender ids null out with a
     * warning — never re-attributed to the acting admin.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function importMessages(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_TICKET_MESSAGES.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            try {
                $remappedId = $this->remaps->resolve($options->sourceInstallation, Module::NAME_LOWER, 'ticket-messages', (int) $sourceId);
                if ($remappedId !== null && $this->messageExists($remappedId)) {
                    if ($options->dryRun) {
                        $this->dryMatched($result, $options);

                        continue;
                    }

                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();
                    } else {
                        $result->recordUpdated();
                    }

                    continue;
                }

                $localTicketId = $this->remaps->resolve($options->sourceInstallation, Module::NAME_LOWER, 'tickets', (int) ($payload[TicketMessageSchema::TICKET_ID] ?? 0));
                if ($localTicketId === null || ! $this->ticketExists($localTicketId)) {
                    $result->recordFailed($label.': unresolved_ticket:'.($payload[TicketMessageSchema::TICKET_ID] ?? '?'));

                    continue;
                }

                $userId = $this->resolveUserId($payload[TicketMessageSchema::USER_ID] ?? null, $options);
                if ($payload[TicketMessageSchema::USER_ID] !== null && $userId === null) {
                    $result->warn($label.': loose_ref_unresolved:user.users:'.($payload[TicketMessageSchema::USER_ID] ?? ''));
                }

                if ($options->dryRun) {
                    $result->recordCreated();

                    continue;
                }

                $values = $payload;
                unset($values['id']);
                $values[TicketMessageSchema::TICKET_ID] = $localTicketId;
                $values[TicketMessageSchema::USER_ID] = $userId;

                $this->units->run(function (TransferRunContext $context) use ($values, $sourceId): void {
                    $localId = (int) DB::table(TicketMessageSchema::TABLE)->insertGetId($values);
                    $this->remaps->record($context->sourceInstallation, Module::NAME_LOWER, 'ticket-messages', (int) $sourceId, $localId);
                });

                $result->recordCreated();
            } catch (Throwable $e) {
                TransferLockSessionLostException::rethrow($e); // session loss is fatal (C7), never a row failure
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * Nullable user attribution through the ledger; dangling mappings
     * null out with the caller's warning (never guessed).
     */
    private function resolveUserId(mixed $sourceUserId, ImportOptions $options): ?int
    {
        if (! is_numeric($sourceUserId)) {
            return null;
        }

        $remapped = $this->remaps->resolve($options->sourceInstallation, 'user', 'users', (int) $sourceUserId);
        if ($remapped !== null && DB::table('auth_users')->where('id', $remapped)->exists()) {
            return $remapped;
        }

        return null;
    }

    private function ticketExists(int $id): bool
    {
        return DB::table(TicketSchema::TABLE)->where('id', $id)->exists();
    }

    private function messageExists(int $id): bool
    {
        return DB::table(TicketMessageSchema::TABLE)->where('id', $id)->exists();
    }

    private function dryMatched(ImportResult $result, ImportOptions $options): void
    {
        if ($options->strategy === TransferStrategyEnum::SKIP) {
            $result->recordSkipped();
        } else {
            $result->recordUpdated();
        }
    }

    /**
     * @param  Builder  $query
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportRowsOf($query): array
    {
        $rows = [];
        $query->chunkById(200, function ($records) use (&$rows): void {
            foreach ($records as $record) {
                $payload = [];
                foreach ($record->getAttributes() as $key => $value) {
                    $payload[$key] = match (true) {
                        $value instanceof BackedEnum => $value->value,
                        $value instanceof CarbonInterface => $value->toIso8601String(),
                        default => $value,
                    };
                }
                $rows[] = [
                    self::KEY_SOURCE_ID => (int) $record->getKey(),
                    self::KEY_PAYLOAD => $payload,
                ];
            }
        });

        return $rows;
    }
}
