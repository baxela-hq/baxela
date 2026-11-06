<?php

namespace Modules\Shipping\Transfer;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Rules\CountryCodeRule;
use Modules\Core\Rules\UniquePair;
use Modules\Core\Schemas\Country\CountrySchema;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Shipping\Actions\Admin\Method\CreateMethodAction;
use Modules\Shipping\Actions\Admin\Method\UpdateMethodAction;
use Modules\Shipping\Actions\Admin\Rate\CreateRateAction;
use Modules\Shipping\Actions\Admin\Rate\UpdateRateAction;
use Modules\Shipping\Actions\Admin\Zone\CreateZoneAction;
use Modules\Shipping\Actions\Admin\Zone\UpdateZoneAction;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Models\Rate;
use Modules\Shipping\Models\Zone;
use Modules\Shipping\Schemas\Method\MethodSchema;
use Modules\Shipping\Schemas\Method\MethodTranslationSchema;
use Modules\Shipping\Schemas\Rate\RateSchema;
use Modules\Shipping\Schemas\Zone\ZoneSchema;
use Throwable;

/**
 * Shipping module's implementation of the data-transfer standard: zones
 * before methods before the rates referencing both, rows matched by
 * natural key (zone name, method code, rate's method × zone pair) and
 * replayed through the admin Actions. Countries travel as codes and
 * translations as language codes — ids are source-store only.
 */
class ShippingTransfer implements ModuleTransferInterface
{
    public const string SECTION_ZONES = 'zones';

    public const string SECTION_METHODS = 'methods';

    public const string SECTION_RATES = 'rates';

    private const int SENTINEL_BASE = -1;

    /**
     * Exported source id → local id (or dry-run sentinel).
     *
     * @var array<int, int>
     */
    private array $zoneIdBySource = [];

    /**
     * Exported source id → local id (or dry-run sentinel).
     *
     * @var array<int, int>
     */
    private array $methodIdBySource = [];

    private int $sentinel = self::SENTINEL_BASE;

    public function __construct(
        protected CreateZoneAction $createZone,
        protected UpdateZoneAction $updateZone,
        protected CreateMethodAction $createMethod,
        protected UpdateMethodAction $updateMethod,
        protected CreateRateAction $createRate,
        protected UpdateRateAction $updateRate,
        protected CoreGatewayInterface $coreGateway,
    ) {}

    public function sections(): array
    {
        return [
            TransferSection::full(self::SECTION_ZONES),
            TransferSection::full(self::SECTION_METHODS),
            TransferSection::full(self::SECTION_RATES),
        ];
    }

    public function exportRows(string $section): array
    {
        return match ($section) {
            self::SECTION_ZONES => $this->exportZones(),
            self::SECTION_METHODS => $this->exportMethods(),
            self::SECTION_RATES => $this->exportRates(),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        return match ($section) {
            self::SECTION_ZONES => $this->importZones($rows, $options),
            self::SECTION_METHODS => $this->importMethods($rows, $options),
            self::SECTION_RATES => $this->importRates($rows, $options),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportZones(): array
    {
        $rows = [];
        Zone::query()
            ->with(ZoneSchema::RES_COUNTRIES)
            ->orderBy(ZoneSchema::ID)
            ->chunkById(200, function ($zones) use (&$rows): void {
                foreach ($zones as $zone) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $zone->getKey(),
                        self::KEY_PAYLOAD => [
                            ZoneSchema::NAME => $zone->{ZoneSchema::NAME},
                            ZoneSchema::IS_ACTIVE => (bool) $zone->{ZoneSchema::IS_ACTIVE},
                            ZoneSchema::POSITION => $zone->{ZoneSchema::POSITION},
                            // Country codes, not store ids — portable.
                            ZoneSchema::COUNTRY_CODES => $zone->{ZoneSchema::RES_COUNTRIES}
                                ->pluck(CountrySchema::CODE)
                                ->values()
                                ->all(),
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportMethods(): array
    {
        $codeByLanguageId = $this->codeByLanguageId();

        $rows = [];
        Method::query()
            ->with(MethodSchema::RES_TRANSLATIONS)
            ->orderBy(MethodSchema::ID)
            ->chunkById(200, function ($methods) use (&$rows, $codeByLanguageId): void {
                foreach ($methods as $method) {
                    $translations = [];
                    foreach ($method->{MethodSchema::RES_TRANSLATIONS} as $translation) {
                        $code = $codeByLanguageId[(int) $translation->{MethodTranslationSchema::LANGUAGE_ID}] ?? null;
                        if ($code !== null) {
                            $translations[] = [
                                MethodTranslationSchema::COL_LANGUAGE => $code,
                                MethodTranslationSchema::NAME => $translation->{MethodTranslationSchema::NAME},
                                MethodTranslationSchema::DESCRIPTION => $translation->{MethodTranslationSchema::DESCRIPTION},
                            ];
                        }
                    }

                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $method->getKey(),
                        self::KEY_PAYLOAD => [
                            MethodSchema::CODE => $method->{MethodSchema::CODE},
                            MethodSchema::IS_ACTIVE => (bool) $method->{MethodSchema::IS_ACTIVE},
                            MethodSchema::POSITION => $method->{MethodSchema::POSITION},
                            MethodSchema::RES_TRANSLATIONS => $translations,
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportRates(): array
    {
        $rows = [];
        Rate::query()
            ->orderBy(RateSchema::ID)
            ->chunkById(200, function ($rates) use (&$rows): void {
                foreach ($rates as $rate) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $rate->getKey(),
                        self::KEY_PAYLOAD => [
                            // Source-store ids; remapped on import.
                            RateSchema::METHOD_ID => (int) $rate->{RateSchema::METHOD_ID},
                            RateSchema::ZONE_ID => (int) $rate->{RateSchema::ZONE_ID},
                            RateSchema::PRICE => $rate->{RateSchema::PRICE},
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importZones(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_ZONES.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $validator = Validator::make($payload, $this->zoneRules());
            if ($validator->fails()) {
                $result->recordFailed($label.': '.$validator->errors()->first());

                continue;
            }

            try {
                $existing = Zone::query()
                    ->where(ZoneSchema::NAME, $payload[ZoneSchema::NAME])
                    ->first();

                if ($options->dryRun) {
                    $this->zoneIdBySource[(int) $sourceId] = $existing?->getKey() !== null
                        ? (int) $existing->getKey()
                        : $this->nextSentinel();
                    $this->recordOutcome($result, $options, $existing !== null);

                    continue;
                }

                if ($existing !== null) {
                    $this->zoneIdBySource[(int) $sourceId] = (int) $existing->getKey();

                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();

                        continue;
                    }

                    $this->updateZone->handle((string) $existing->getKey(), $payload);
                    $result->recordUpdated();

                    continue;
                }

                $record = $this->createZone->handle($payload);
                $this->zoneIdBySource[(int) $sourceId] = (int) $record->getKey();
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
    private function importMethods(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();
        $languageIdByCode = $this->languageIdByCode();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_METHODS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $validator = Validator::make($payload, $this->methodShapeRules());
            if ($validator->fails()) {
                $result->recordFailed($label.': '.$validator->errors()->first());

                continue;
            }

            try {
                $item = $this->resolveMethodTranslations($payload, $languageIdByCode, $result, $label);
                if ($item === null) {
                    continue;
                }

                $existing = Method::query()
                    ->where(MethodSchema::CODE, $payload[MethodSchema::CODE])
                    ->first();

                $validator = Validator::make($item, $this->methodRules($existing?->getKey()));
                if ($validator->fails()) {
                    $result->recordFailed($label.': '.$validator->errors()->first());

                    continue;
                }

                if ($options->dryRun) {
                    $this->methodIdBySource[(int) $sourceId] = $existing?->getKey() !== null
                        ? (int) $existing->getKey()
                        : $this->nextSentinel();
                    $this->recordOutcome($result, $options, $existing !== null);

                    continue;
                }

                if ($existing !== null) {
                    $this->methodIdBySource[(int) $sourceId] = (int) $existing->getKey();

                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();

                        continue;
                    }

                    $this->updateMethod->handle((string) $existing->getKey(), $item);
                    $result->recordUpdated();

                    continue;
                }

                $record = $this->createMethod->handle($item);
                $this->methodIdBySource[(int) $sourceId] = (int) $record->getKey();
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
    private function importRates(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_RATES.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $methodId = $this->remap($payload[RateSchema::METHOD_ID] ?? null, $this->methodIdBySource);
            $zoneId = $this->remap($payload[RateSchema::ZONE_ID] ?? null, $this->zoneIdBySource);

            if ($methodId === null || $zoneId === null) {
                $result->recordFailed($label.': '.($methodId === null ? 'unresolved_method' : 'unresolved_zone'));

                continue;
            }

            $payload[RateSchema::METHOD_ID] = $methodId;
            $payload[RateSchema::ZONE_ID] = $zoneId;

            try {
                $existing = Rate::query()
                    ->where([
                        [RateSchema::METHOD_ID, $methodId],
                        [RateSchema::ZONE_ID, $zoneId],
                    ])
                    ->first();

                $validator = Validator::make($payload, $this->rateRules($options, $payload, $existing?->getKey()));
                if ($validator->fails()) {
                    $result->recordFailed($label.': '.$validator->errors()->first());

                    continue;
                }

                if ($options->dryRun) {
                    $this->recordOutcome($result, $options, $existing !== null);

                    continue;
                }

                if ($existing !== null) {
                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();

                        continue;
                    }

                    $this->updateRate->handle((string) $existing->getKey(), $payload);
                    $result->recordUpdated();

                    continue;
                }

                $this->createRate->handle($payload);
                $result->recordCreated();
            } catch (Throwable $e) {
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function zoneRules(): array
    {
        return [
            ZoneSchema::NAME => ['required', 'string', 'max:255'],
            ZoneSchema::IS_ACTIVE => ['required', 'boolean'],
            ZoneSchema::POSITION => ['nullable', 'numeric', 'max:255'],
            ZoneSchema::COUNTRY_CODES => ['nullable', 'array'],
            ZoneSchema::COUNTRY_CODES.'.*' => ['string', 'size:2', new CountryCodeRule],
        ];
    }

    /**
     * Shape check before the language codes are resolved to ids.
     *
     * @return array<string, list<mixed>>
     */
    private function methodShapeRules(): array
    {
        return [
            MethodSchema::CODE => ['required', 'string', 'max:255'],
            MethodSchema::IS_ACTIVE => ['required', 'boolean'],
            MethodSchema::POSITION => ['nullable', 'numeric', 'max:255'],
            MethodSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            MethodSchema::RES_TRANSLATIONS.'.*.'.MethodTranslationSchema::COL_LANGUAGE => ['required', 'string', 'distinct', 'size:2'],
            MethodSchema::RES_TRANSLATIONS.'.*.'.MethodTranslationSchema::NAME => ['required', 'string', 'max:255'],
            MethodSchema::RES_TRANSLATIONS.'.*.'.MethodTranslationSchema::DESCRIPTION => ['nullable', 'string'],
        ];
    }

    /**
     * Mirrors MethodRequest (uniqueness of the code, ignoring the record
     * an UPDATE strategy would overwrite) against the resolved item.
     *
     * @return array<string, list<mixed>>
     */
    private function methodRules(int|string|null $ignoreId): array
    {
        return [
            MethodSchema::CODE => ['required', 'string', 'max:255',
                Rule::unique(MethodSchema::TABLE, MethodSchema::CODE)->ignore($ignoreId)],
            MethodSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
        ];
    }

    /**
     * Mirrors RateRequest: one rate per method × zone pair, ignoring the
     * record an UPDATE strategy would overwrite. Dry runs may reference
     * sections a dry run itself would create (sentinel ids), so the
     * exists checks are deferred to the remap step.
     *
     * @param  array<string, mixed>  $payload  Method/zone ids already remapped
     * @return array<string, list<mixed>>
     */
    private function rateRules(ImportOptions $options, array $payload, int|string|null $ignoreId): array
    {
        $rules = [
            RateSchema::METHOD_ID => $options->dryRun
                ? ['required', 'integer']
                : ['required', 'integer', Rule::exists(MethodSchema::TABLE, MethodSchema::ID)],
            RateSchema::ZONE_ID => $options->dryRun
                ? ['required', 'integer']
                : ['required', 'integer', Rule::exists(ZoneSchema::TABLE, ZoneSchema::ID)],
            RateSchema::PRICE => ['required', 'numeric', 'min:0',
                new UniquePair(RateSchema::TABLE, [
                    RateSchema::METHOD_ID => $payload[RateSchema::METHOD_ID],
                    RateSchema::ZONE_ID => $payload[RateSchema::ZONE_ID],
                ], is_null($ignoreId) ? null : (int) $ignoreId)],
        ];

        return $rules;
    }

    /**
     * Replace language codes with ids; failed rows are recorded.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, int>  $languageIdByCode
     * @return array<string, mixed>|null
     */
    private function resolveMethodTranslations(
        array $payload,
        array $languageIdByCode,
        ImportResult $result,
        string $label,
    ): ?array {
        $translations = [];
        foreach ($payload[MethodSchema::RES_TRANSLATIONS] as $translation) {
            $languageId = $languageIdByCode[$translation[MethodTranslationSchema::COL_LANGUAGE]] ?? null;
            if ($languageId === null) {
                $result->recordFailed($label.': unresolved_language:'.$translation[MethodTranslationSchema::COL_LANGUAGE]);

                return null;
            }

            $translations[] = [
                MethodTranslationSchema::LANGUAGE_ID => $languageId,
                MethodTranslationSchema::NAME => $translation[MethodTranslationSchema::NAME],
                MethodTranslationSchema::DESCRIPTION => $translation[MethodTranslationSchema::DESCRIPTION] ?? null,
            ];
        }

        $payload[MethodSchema::RES_TRANSLATIONS] = $translations;

        return $payload;
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

    /**
     * @param  array<int, int>  $map
     */
    private function remap(mixed $sourceId, array $map): ?int
    {
        return is_numeric($sourceId) ? ($map[(int) $sourceId] ?? null) : null;
    }

    /**
     * @return array<string, int>
     */
    private function languageIdByCode(): array
    {
        $codes = $this->coreGateway->getActiveLanguages()
            ->pluck(LanguageSchema::CODE)
            ->all();

        return $this->coreGateway->getLanguageIdsByCodes($codes);
    }

    /**
     * @return array<int, string>
     */
    private function codeByLanguageId(): array
    {
        $codeByLanguageId = [];
        foreach ($this->coreGateway->getActiveLanguages() as $language) {
            $codeByLanguageId[(int) $language->getKey()] = $language->{LanguageSchema::CODE};
        }

        return $codeByLanguageId;
    }

    private function nextSentinel(): int
    {
        return $this->sentinel--;
    }
}
