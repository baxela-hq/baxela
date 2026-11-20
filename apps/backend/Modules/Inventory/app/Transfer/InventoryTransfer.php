<?php

namespace Modules\Inventory\Transfer;

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
use Modules\Core\Support\Transfer\CrossModuleResolver;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;
use Throwable;

/**
 * Inventory module's implementation of the data-transfer standard: the
 * stock ledger, one row per catalog variant.
 *
 * Variants travel as the exporting store's source ids and are validated
 * through the Catalog gateway (never by touching Catalog's tables) — a
 * file replayed into a different catalog fails those rows instead of
 * silently restocking the wrong variants. Replay goes through the
 * module's own gateway primitive (upsertStock), so the ledger keeps its
 * one-row-per-variant invariant.
 */
class InventoryTransfer implements ModuleTransferInterface
{
    public const string SECTION_STOCKS = 'stocks';

    public function __construct(
        protected InventoryGatewayInterface $inventoryGateway,
        protected CatalogGatewayInterface $catalogGateway,
        protected CrossModuleResolver $variantResolver,
    ) {}

    public function sections(): array
    {
        return [
            TransferSection::full(self::SECTION_STOCKS),
        ];
    }

    public function exportRows(string $section): array
    {
        if ($section !== self::SECTION_STOCKS) {
            throw new InvalidArgumentException("Unknown section [{$section}].");
        }

        $rows = [];
        InventoryStock::query()
            ->orderBy(InventoryStockSchema::ID)
            ->chunkById(200, function ($stocks) use (&$rows): void {
                foreach ($stocks as $stock) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $stock->getKey(),
                        self::KEY_PAYLOAD => [
                            // Source-store variant id; the importing store
                            // resolves or fails per row.
                            InventoryStockSchema::VARIANT_ID => (int) $stock->{InventoryStockSchema::VARIANT_ID},
                            InventoryStockSchema::QUANTITY => (int) $stock->{InventoryStockSchema::QUANTITY},
                        ],
                    ];
                }
            });

        return $rows;
    }

    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        if ($section !== self::SECTION_STOCKS) {
            throw new InvalidArgumentException("Unknown section [{$section}].");
        }

        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_STOCKS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;

            if (! is_array($payload)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $validator = Validator::make($payload, [
                InventoryStockSchema::VARIANT_ID => ['required', 'integer'],
                InventoryStockSchema::QUANTITY => ['required', 'integer', 'min:0'],
            ]);
            if ($validator->fails()) {
                $result->recordFailed($label.': '.$validator->errors()->first());

                continue;
            }

            try {
                $quantity = (int) $payload[InventoryStockSchema::QUANTITY];

                // The variant reference resolves through the C2 tiers
                // (remap → raw, raw gated by the file's identity) before
                // the ledger invariant check.
                $variantResolution = $this->variantResolver->resolve(
                    $options,
                    'catalog',
                    'variants',
                    (int) $payload[InventoryStockSchema::VARIANT_ID],
                    null,
                    fn (int $id): bool => $this->catalogGateway->variantExists($id),
                    nullable: false,
                );

                if ($variantResolution->failed()) {
                    $result->recordFailed($label.': '.$variantResolution->error);

                    continue;
                }

                if ($variantResolution->warning !== null) {
                    $result->warn($label.': '.$variantResolution->warning);
                }

                $variantId = (int) $variantResolution->localId;

                // One stock row per variant — the ledger's natural key.
                $existing = InventoryStock::query()
                    ->where(InventoryStockSchema::VARIANT_ID, $variantId)
                    ->first();

                if ($options->dryRun) {
                    $this->recordOutcome($result, $options, $existing !== null);

                    continue;
                }

                if ($existing !== null && $options->strategy === TransferStrategyEnum::SKIP) {
                    $result->recordSkipped();

                    continue;
                }

                // The gateway's own upsert keeps the ledger invariant.
                $this->inventoryGateway->upsertStock($variantId, $quantity);

                if ($existing !== null) {
                    $result->recordUpdated();
                } else {
                    $result->recordCreated();
                }
            } catch (Throwable $e) {
                TransferLockSessionLostException::rethrow($e); // session loss is fatal (C7), never a row failure
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * Count a row's would-be outcome for a dry run.
     */
    private function recordOutcome(ImportResult $result, ImportOptions $options, bool $exists): void
    {
        if (! $exists) {
            $result->recordCreated();
        } elseif ($options->strategy === TransferStrategyEnum::SKIP) {
            $result->recordSkipped();
        } else {
            $result->recordUpdated();
        }
    }
}
