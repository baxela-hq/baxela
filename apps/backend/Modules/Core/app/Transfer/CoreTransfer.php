<?php

namespace Modules\Core\Transfer;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Country\CountrySchema;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Core\Schemas\Language\LanguageSchema;
use Throwable;

/**
 * Core module's implementation of the data-transfer standard: the
 * reference data every other store needs before anything else can be
 * imported (countries feed CountryCodeRule, languages feed every
 * translation, currencies feed display).
 *
 * Languages and countries are seeder-owned (no admin create actions), so
 * imports upsert by their natural keys mirroring the seeder's defaults;
 * the single default flags demote every other row, exactly like the
 * settings sync does.
 */
class CoreTransfer implements ModuleTransferInterface
{
    public const string SECTION_LANGUAGES = 'languages';

    public const string SECTION_CURRENCIES = 'currencies';

    public const string SECTION_COUNTRIES = 'countries';

    public function sections(): array
    {
        return [
            TransferSection::full(self::SECTION_LANGUAGES),
            TransferSection::full(self::SECTION_CURRENCIES),
            TransferSection::full(self::SECTION_COUNTRIES),
        ];
    }

    public function exportRows(string $section): array
    {
        return match ($section) {
            self::SECTION_LANGUAGES => $this->exportLanguages(),
            self::SECTION_CURRENCIES => $this->exportCurrencies(),
            self::SECTION_COUNTRIES => $this->exportCountries(),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        return match ($section) {
            self::SECTION_LANGUAGES => $this->importLanguages($rows, $options),
            self::SECTION_CURRENCIES => $this->importCurrencies($rows, $options),
            self::SECTION_COUNTRIES => $this->importCountries($rows, $options),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportLanguages(): array
    {
        $rows = [];
        Language::query()
            ->orderBy(LanguageSchema::ID)
            ->chunkById(200, function ($languages) use (&$rows): void {
                foreach ($languages as $language) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $language->getKey(),
                        self::KEY_PAYLOAD => [
                            LanguageSchema::LOCALE => $language->{LanguageSchema::LOCALE},
                            LanguageSchema::NAME => $language->{LanguageSchema::NAME},
                            LanguageSchema::NATIVE_NAME => $language->{LanguageSchema::NATIVE_NAME},
                            LanguageSchema::CODE => $language->{LanguageSchema::CODE},
                            LanguageSchema::CODE3 => $language->{LanguageSchema::CODE3},
                            LanguageSchema::IS_RTL => (bool) $language->{LanguageSchema::IS_RTL},
                            LanguageSchema::IS_ACTIVE => (bool) $language->{LanguageSchema::IS_ACTIVE},
                            LanguageSchema::IS_DEFAULT => (bool) $language->{LanguageSchema::IS_DEFAULT},
                            LanguageSchema::POSITION => $language->{LanguageSchema::POSITION},
                            LanguageSchema::DATE_FORMAT => $language->{LanguageSchema::DATE_FORMAT},
                            LanguageSchema::TIME_FORMAT => $language->{LanguageSchema::TIME_FORMAT},
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportCurrencies(): array
    {
        $rows = [];
        Currency::query()
            ->orderBy(CurrencySchema::ID)
            ->chunkById(200, function ($currencies) use (&$rows): void {
                foreach ($currencies as $currency) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $currency->getKey(),
                        self::KEY_PAYLOAD => [
                            CurrencySchema::CODE => $currency->{CurrencySchema::CODE},
                            CurrencySchema::NAME => $currency->{CurrencySchema::NAME},
                            CurrencySchema::NATIVE_NAME => $currency->{CurrencySchema::NATIVE_NAME},
                            CurrencySchema::DECIMAL_PLACES => $currency->{CurrencySchema::DECIMAL_PLACES},
                            CurrencySchema::SYMBOL => $currency->{CurrencySchema::SYMBOL},
                            CurrencySchema::IS_SYMBOL_RIGHT => (bool) $currency->{CurrencySchema::IS_SYMBOL_RIGHT},
                            CurrencySchema::IS_DEFAULT => (bool) $currency->{CurrencySchema::IS_DEFAULT},
                            CurrencySchema::EXCHANGE_RATE => $currency->{CurrencySchema::EXCHANGE_RATE},
                            CurrencySchema::IS_ACTIVE => (bool) $currency->{CurrencySchema::IS_ACTIVE},
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportCountries(): array
    {
        $rows = [];
        Country::query()
            ->orderBy(CountrySchema::ID)
            ->chunkById(200, function ($countries) use (&$rows): void {
                foreach ($countries as $country) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $country->getKey(),
                        self::KEY_PAYLOAD => [
                            CountrySchema::CODE => $country->{CountrySchema::CODE},
                            CountrySchema::CODE3 => $country->{CountrySchema::CODE3},
                            CountrySchema::NAME => $country->{CountrySchema::NAME},
                            CountrySchema::NATIVE_NAME => $country->{CountrySchema::NATIVE_NAME},
                            CountrySchema::PHONE_CODE => $country->{CountrySchema::PHONE_CODE},
                            CountrySchema::EMOJI => $country->{CountrySchema::EMOJI},
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importLanguages(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_LANGUAGES.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;

            if (! is_array($payload)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $validator = Validator::make($payload, $this->languageRules());
            if ($validator->fails()) {
                $result->recordFailed($label.': '.$validator->errors()->first());

                continue;
            }

            try {
                $existing = Language::query()
                    ->where(LanguageSchema::CODE, $payload[LanguageSchema::CODE])
                    ->first();

                if ($options->dryRun) {
                    $this->recordOutcome($result, $options, $existing !== null);

                    continue;
                }

                $wasCreated = false;
                if ($existing !== null) {
                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();

                        continue;
                    }

                    $existing->update($payload);
                } else {
                    // Seeder-owned data: new rows mirror the seeder defaults.
                    $existing = Language::query()->create($payload);
                    $wasCreated = true;
                }

                if ($payload[LanguageSchema::IS_DEFAULT]) {
                    // Exactly one default language, like the settings sync.
                    Language::query()
                        ->whereKeyNot($existing->getKey())
                        ->update([LanguageSchema::IS_DEFAULT => false]);
                    $existing->update([LanguageSchema::IS_DEFAULT => true]);
                }

                if ($wasCreated) {
                    $result->recordCreated();
                } else {
                    $result->recordUpdated();
                }
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
    private function importCurrencies(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_CURRENCIES.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;

            if (! is_array($payload)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            // Codes are stored uppercase, exactly like CreateCurrencyAction.
            $payload[CurrencySchema::CODE] = strtoupper(trim((string) ($payload[CurrencySchema::CODE] ?? '')));

            $validator = Validator::make($payload, $this->currencyRules());
            if ($validator->fails()) {
                $result->recordFailed($label.': '.$validator->errors()->first());

                continue;
            }

            try {
                $existing = Currency::query()
                    ->where(CurrencySchema::CODE, $payload[CurrencySchema::CODE])
                    ->first();

                if ($options->dryRun) {
                    $this->recordOutcome($result, $options, $existing !== null);

                    continue;
                }

                $wasCreated = false;
                if ($existing !== null) {
                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();

                        continue;
                    }

                    $existing->update($payload);
                } else {
                    $existing = Currency::query()->create($payload);
                    $wasCreated = true;
                }

                if ($payload[CurrencySchema::IS_DEFAULT]) {
                    Currency::query()
                        ->whereKeyNot($existing->getKey())
                        ->update([CurrencySchema::IS_DEFAULT => false]);
                    $existing->update([CurrencySchema::IS_DEFAULT => true]);
                }

                if ($wasCreated) {
                    $result->recordCreated();
                } else {
                    $result->recordUpdated();
                }
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
    private function importCountries(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_COUNTRIES.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;

            if (! is_array($payload)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            try {
                // Match before validating so the code3 unique rule can
                // ignore the record an UPDATE would overwrite.
                $existing = Country::query()
                    ->where(CountrySchema::CODE, $payload[CountrySchema::CODE] ?? null)
                    ->first();

                $validator = Validator::make($payload, $this->countryRules($existing?->getKey()));
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

                    $existing->update($payload);
                    $result->recordUpdated();

                    continue;
                }

                Country::query()->create($payload);
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
    private function languageRules(): array
    {
        return [
            LanguageSchema::LOCALE => ['required', 'string', 'max:5'],
            LanguageSchema::NAME => ['required', 'string', 'max:255'],
            LanguageSchema::NATIVE_NAME => ['nullable', 'string', 'max:255'],
            LanguageSchema::CODE => ['required', 'string', 'size:2'],
            LanguageSchema::CODE3 => ['required', 'string', 'size:3'],
            LanguageSchema::IS_RTL => ['required', 'boolean'],
            LanguageSchema::IS_ACTIVE => ['required', 'boolean'],
            LanguageSchema::IS_DEFAULT => ['required', 'boolean'],
            LanguageSchema::POSITION => ['nullable', 'integer'],
            LanguageSchema::DATE_FORMAT => ['nullable', 'string', 'max:255'],
            LanguageSchema::TIME_FORMAT => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function currencyRules(): array
    {
        return [
            CurrencySchema::CODE => ['required', 'string', 'size:3'],
            CurrencySchema::NAME => ['required', 'string', 'max:255'],
            CurrencySchema::NATIVE_NAME => ['nullable', 'string', 'max:255'],
            CurrencySchema::DECIMAL_PLACES => ['required', 'integer', 'min:0', 'max:8'],
            CurrencySchema::SYMBOL => ['nullable', 'string', 'max:8'],
            CurrencySchema::IS_SYMBOL_RIGHT => ['required', 'boolean'],
            CurrencySchema::IS_DEFAULT => ['required', 'boolean'],
            CurrencySchema::EXCHANGE_RATE => ['required', 'numeric', 'min:0'],
            CurrencySchema::IS_ACTIVE => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function countryRules(?int $ignoreId): array
    {
        return [
            CountrySchema::CODE => ['required', 'string', 'size:2'],
            CountrySchema::CODE3 => [
                'required', 'string', 'size:3',
                // code3 carries its own unique index — a colliding row is
                // a per-row failure, never a silent overwrite
                Rule::unique(CountrySchema::TABLE, CountrySchema::CODE3)->ignore($ignoreId),
            ],
            CountrySchema::NAME => ['required', 'string', 'max:255'],
            CountrySchema::NATIVE_NAME => ['nullable', 'string', 'max:255'],
            CountrySchema::PHONE_CODE => ['nullable', 'string', 'max:8'],
            CountrySchema::EMOJI => ['nullable', 'string', 'max:8'],
        ];
    }

    /**
     * Count a row's outcome; update/create is decided by the caller.
     */
    private function recordOutcome(ImportResult $result, ImportOptions $options, bool $exists): void
    {
        if ($exists && $options->strategy === TransferStrategyEnum::SKIP) {
            $result->recordSkipped();

            return;
        }

        $result->recordUpdated();
    }
}
