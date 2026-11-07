<?php

namespace Modules\Support\Transfer;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;

/**
 * Support module's implementation of the data-transfer standard:
 * export-only. Tickets and their conversations are per-user history of
 * the exporting store — replaying them would forge correspondence.
 */
class SupportTransfer implements ModuleTransferInterface
{
    public const string SECTION_TICKETS = 'tickets';

    public const string SECTION_TICKET_MESSAGES = 'ticket-messages';

    public function sections(): array
    {
        return [
            TransferSection::exportOnly(self::SECTION_TICKETS),
            TransferSection::exportOnly(self::SECTION_TICKET_MESSAGES),
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

    /**
     * Export-only: the Transfer engine skips these sections before it
     * ever reaches here.
     */
    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        throw new InvalidArgumentException("Section [{$section}] is export-only.");
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
                        $value instanceof DateTimeInterface => $value->toIso8601String(),
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
