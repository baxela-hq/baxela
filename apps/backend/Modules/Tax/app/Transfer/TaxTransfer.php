<?php

namespace Modules\Tax\Transfer;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Rules\CountryCodeRule;
use Modules\Tax\Actions\Admin\TaxClass\CreateTaxClassAction;
use Modules\Tax\Actions\Admin\TaxClass\UpdateTaxClassAction;
use Modules\Tax\Actions\Admin\TaxRate\CreateTaxRateAction;
use Modules\Tax\Actions\Admin\TaxRate\UpdateTaxRateAction;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;
use Throwable;

/**
 * Tax module's implementation of the data-transfer standard: tax classes
 * before the rates referencing them, rows matching existing records by
 * natural key (class name, rate's country × class) and replayed through
 * the admin Actions so the single-flag invariants stay intact.
 */
class TaxTransfer implements ModuleTransferInterface
{
    public const string SECTION_TAX_CLASSES = 'tax-classes';

    public const string SECTION_TAX_RATES = 'tax-rates';

    /** Below-zero ids stand for rows a dry run would still create. */
    private const int SENTINEL_BASE = -1;

    /**
     * Exported tax-class source id → local id (or dry-run sentinel),
     * built while the classes section replays so the rates section can
     * remap its references.
     *
     * @var array<int, int>
     */
    private array $taxClassIdBySource = [];

    private int $sentinel = self::SENTINEL_BASE;

    public function __construct(
        protected CreateTaxClassAction $createTaxClass,
        protected UpdateTaxClassAction $updateTaxClass,
        protected CreateTaxRateAction $createTaxRate,
        protected UpdateTaxRateAction $updateTaxRate,
    ) {}

    public function sections(): array
    {
        return [
            TransferSection::full(self::SECTION_TAX_CLASSES),
            TransferSection::full(self::SECTION_TAX_RATES),
        ];
    }

    public function exportRows(string $section): array
    {
        return match ($section) {
            self::SECTION_TAX_CLASSES => $this->exportTaxClasses(),
            self::SECTION_TAX_RATES => $this->exportTaxRates(),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        return match ($section) {
            self::SECTION_TAX_CLASSES => $this->importTaxClasses($rows, $options),
            self::SECTION_TAX_RATES => $this->importTaxRates($rows, $options),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportTaxClasses(): array
    {
        $rows = [];
        TaxClass::query()
            ->orderBy(TaxClassSchema::ID)
            ->chunkById(200, function ($classes) use (&$rows): void {
                foreach ($classes as $class) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $class->getKey(),
                        self::KEY_PAYLOAD => [
                            TaxClassSchema::NAME => $class->{TaxClassSchema::NAME},
                            TaxClassSchema::IS_DEFAULT => (bool) $class->{TaxClassSchema::IS_DEFAULT},
                            TaxClassSchema::IS_SHIPPING => (bool) $class->{TaxClassSchema::IS_SHIPPING},
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportTaxRates(): array
    {
        $rows = [];
        TaxRate::query()
            ->orderBy(TaxRateSchema::ID)
            ->chunkById(200, function ($rates) use (&$rows): void {
                foreach ($rates as $rate) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $rate->getKey(),
                        self::KEY_PAYLOAD => [
                            TaxRateSchema::NAME => $rate->{TaxRateSchema::NAME},
                            TaxRateSchema::COUNTRY_CODE => $rate->{TaxRateSchema::COUNTRY_CODE},
                            // Source-store class id; remapped on import.
                            TaxRateSchema::TAX_CLASS_ID => (int) $rate->{TaxRateSchema::TAX_CLASS_ID},
                            // decimal:4 string — exact round-trip, never a float
                            TaxRateSchema::RATE => $rate->{TaxRateSchema::RATE},
                            TaxRateSchema::IS_ACTIVE => (bool) $rate->{TaxRateSchema::IS_ACTIVE},
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importTaxClasses(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_TAX_CLASSES.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $validator = Validator::make($payload, $this->classRules());
            if ($validator->fails()) {
                $result->recordFailed($label.': '.$validator->errors()->first());

                continue;
            }

            try {
                $existing = TaxClass::query()
                    ->where(TaxClassSchema::NAME, $payload[TaxClassSchema::NAME])
                    ->first();

                if ($options->dryRun) {
                    // Register the would-be id so rate rows referencing
                    // this class stay resolvable for the rest of the run.
                    $this->taxClassIdBySource[(int) $sourceId] = $existing?->getKey() !== null
                        ? (int) $existing->getKey()
                        : $this->nextSentinel();

                    if ($existing === null) {
                        $result->recordCreated();
                    } elseif ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();
                    } else {
                        $result->recordUpdated();
                    }

                    continue;
                }

                if ($existing !== null) {
                    $this->taxClassIdBySource[(int) $sourceId] = (int) $existing->getKey();

                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();

                        continue;
                    }

                    $this->updateTaxClass->handle((string) $existing->getKey(), $payload);
                    $result->recordUpdated();

                    continue;
                }

                $record = $this->createTaxClass->handle($payload);
                $this->taxClassIdBySource[(int) $sourceId] = (int) $record->getKey();
                $result->recordCreated();
            } catch (Throwable $e) {
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importTaxRates(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_TAX_RATES.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $sourceClassId = $payload[TaxRateSchema::TAX_CLASS_ID] ?? null;
            $localClassId = is_numeric($sourceClassId)
                ? ($this->taxClassIdBySource[(int) $sourceClassId] ?? null)
                : null;

            if ($localClassId === null) {
                $result->recordFailed($label.': unresolved_tax_class');

                continue;
            }

            $payload[TaxRateSchema::TAX_CLASS_ID] = $localClassId;

            try {
                $existing = TaxRate::query()
                    ->where([
                        [TaxRateSchema::COUNTRY_CODE, $payload[TaxRateSchema::COUNTRY_CODE] ?? null],
                        [TaxRateSchema::TAX_CLASS_ID, $localClassId],
                    ])
                    ->first();

                $validator = Validator::make($payload, $this->rateRules($options, $payload, $existing?->getKey()));
                if ($validator->fails()) {
                    $result->recordFailed($label.': '.$validator->errors()->first());

                    continue;
                }

                if ($options->dryRun) {
                    if ($existing === null) {
                        $result->recordCreated();
                    } elseif ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();
                    } else {
                        $result->recordUpdated();
                    }

                    continue;
                }

                if ($existing !== null) {
                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();

                        continue;
                    }

                    $this->updateTaxRate->handle((string) $existing->getKey(), $payload);
                    $result->recordUpdated();

                    continue;
                }

                $this->createTaxRate->handle($payload);
                $result->recordCreated();
            } catch (Throwable $e) {
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * @return array<string, list<string>>
     */
    private function classRules(): array
    {
        return [
            TaxClassSchema::NAME => ['required', 'string', 'max:255'],
            TaxClassSchema::IS_DEFAULT => ['required', 'boolean'],
            TaxClassSchema::IS_SHIPPING => ['required', 'boolean'],
        ];
    }

    /**
     * Mirrors TaxRateRequest: uniqueness is scoped to the country × class
     * pair, ignoring the record an UPDATE strategy would overwrite.
     * Dry runs may reference classes a dry run itself would create
     * (sentinel ids), so the exists check is deferred to the remap step.
     *
     * @param  array<string, mixed>  $payload  Class id already remapped
     * @return array<string, list<mixed>>
     */
    private function rateRules(ImportOptions $options, array $payload, int|string|null $ignoreId): array
    {
        return [
            TaxRateSchema::NAME => ['required', 'string', 'max:255'],
            TaxRateSchema::COUNTRY_CODE => [
                'required',
                'string',
                'size:2',
                new CountryCodeRule,
                // one rate per country × class
                Rule::unique(TaxRateSchema::TABLE, TaxRateSchema::COUNTRY_CODE)
                    ->where(TaxRateSchema::TAX_CLASS_ID, $payload[TaxRateSchema::TAX_CLASS_ID])
                    ->ignore($ignoreId),
            ],
            TaxRateSchema::TAX_CLASS_ID => $options->dryRun
                ? ['required', 'integer']
                : ['required', 'integer', Rule::exists(TaxClassSchema::TABLE, TaxClassSchema::ID)],
            // percentage, 0–100, at most 4 fraction digits so the stored
            // decimal(7,4) never has to round silently
            TaxRateSchema::RATE => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                'regex:/^\d{1,3}(\.\d{1,4})?$/',
            ],
            TaxRateSchema::IS_ACTIVE => ['required', 'boolean'],
        ];
    }

    private function nextSentinel(): int
    {
        return $this->sentinel--;
    }
}
