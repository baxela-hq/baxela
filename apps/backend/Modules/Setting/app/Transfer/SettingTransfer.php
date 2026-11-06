<?php

namespace Modules\Setting\Transfer;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Setting\Actions\Admin\Setting\UpdateSettingAction;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Schemas\Translation\TranslationSchema;
use Throwable;

/**
 * Setting module's implementation of the data-transfer standard.
 *
 * Settings are enum-keyed rows the module seeder plants, so the natural
 * key is the name: missing names fail per-row (a store must be seeded
 * before it can receive settings), existing ones replay through the
 * admin update Action — which keeps the core default-language/currency
 * flags and the timezone override in sync exactly like a manual save.
 *
 * The two id-reference settings travel as codes for portability: the
 * export carries `en`/`EUR` in place of the language_id/currency_id
 * value, and the import resolves the code to the local id — the same
 * portability rule translations follow with language codes.
 */
class SettingTransfer implements ModuleTransferInterface
{
    public const string SECTION_SETTINGS = 'settings';

    public function __construct(
        protected UpdateSettingAction $updateSetting,
        protected CoreGatewayInterface $coreGateway,
    ) {}

    public function sections(): array
    {
        return [
            TransferSection::full(self::SECTION_SETTINGS),
        ];
    }

    public function exportRows(string $section): array
    {
        if ($section !== self::SECTION_SETTINGS) {
            throw new InvalidArgumentException("Unknown section [{$section}].");
        }

        $codeByLanguageId = [];
        foreach ($this->coreGateway->getActiveLanguages() as $language) {
            $codeByLanguageId[(int) $language->getKey()] = $language->{LanguageSchema::CODE};
        }

        $rows = [];
        Setting::query()
            ->with(SettingSchema::RES_TRANSLATIONS)
            ->orderBy(SettingSchema::ID)
            ->chunkById(200, function ($settings) use (&$rows, $codeByLanguageId): void {
                foreach ($settings as $setting) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $setting->getKey(),
                        self::KEY_PAYLOAD => [
                            SettingSchema::NAME => $setting->{SettingSchema::NAME},
                            SettingSchema::VALUE => $this->exportValue($setting),
                            SettingSchema::RES_TRANSLATIONS => $this->exportTranslations($setting, $codeByLanguageId),
                        ],
                    ];
                }
            });

        return $rows;
    }

    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        if ($section !== self::SECTION_SETTINGS) {
            throw new InvalidArgumentException("Unknown section [{$section}].");
        }

        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_SETTINGS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;

            if (! is_array($payload)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $validator = Validator::make($payload, $this->rules());
            if ($validator->fails()) {
                $result->recordFailed($label.': '.$validator->errors()->first());

                continue;
            }

            try {
                $item = $this->resolveItem($payload, $result, $label);
                if ($item === null) {
                    continue;
                }

                $existing = Setting::query()
                    ->where(SettingSchema::NAME, $payload[SettingSchema::NAME])
                    ->first();

                if ($existing === null) {
                    // Settings come from the module seeder; an unknown
                    // name means the importing store does not know this
                    // setting at all.
                    $result->recordFailed($label.': unknown_setting:'.$payload[SettingSchema::NAME]);

                    continue;
                }

                if ($options->dryRun) {
                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();
                    } else {
                        $result->recordUpdated();
                    }

                    continue;
                }

                if ($options->strategy === TransferStrategyEnum::SKIP) {
                    $result->recordSkipped();

                    continue;
                }

                $this->updateSetting->handle([$item]);
                $result->recordUpdated();
            } catch (Throwable $e) {
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * Id-reference settings travel as codes; everything else as-is.
     */
    private function exportValue(Setting $setting): string
    {
        $name = $setting->{SettingSchema::NAME} instanceof SettingNameEnum
            ? $setting->{SettingSchema::NAME}->value
            : (string) $setting->{SettingSchema::NAME};

        $value = (string) $setting->{SettingSchema::VALUE};

        if ($name === SettingNameEnum::LANGUAGE_ID->value) {
            foreach ($this->coreGateway->getActiveLanguages() as $language) {
                if ((int) $language->getKey() === (int) $value) {
                    return (string) $language->{LanguageSchema::CODE};
                }
            }

            return $value;
        }

        if ($name === SettingNameEnum::CURRENCY_ID->value) {
            foreach ($this->coreGateway->getActiveCurrencies() as $currency) {
                if ((int) $currency->getKey() === (int) $value) {
                    return (string) $currency->{CurrencySchema::CODE};
                }
            }

            return $value;
        }

        return $value;
    }

    /**
     * @param  array<int, mixed>  $codeByLanguageId
     * @return list<array<string, mixed>>
     */
    private function exportTranslations(Setting $setting, array $codeByLanguageId): array
    {
        $translations = [];
        foreach ($setting->{SettingSchema::RES_TRANSLATIONS} as $translation) {
            $code = $codeByLanguageId[(int) $translation->{TranslationSchema::LANGUAGE_ID}] ?? null;
            if ($code !== null) {
                $translations[] = [
                    TranslationSchema::LANGUAGE => $code,
                    TranslationSchema::VALUE => $translation->{TranslationSchema::VALUE},
                ];
            }
        }

        return $translations;
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        return [
            SettingSchema::NAME => ['required', 'string', new Enum(SettingNameEnum::class)],
            SettingSchema::VALUE => ['required', 'string'],
            SettingSchema::RES_TRANSLATIONS => ['nullable', 'array'],
            SettingSchema::RES_TRANSLATIONS.'.*.'.TranslationSchema::LANGUAGE => ['required', 'string'],
            SettingSchema::RES_TRANSLATIONS.'.*.'.TranslationSchema::VALUE => ['required', 'string'],
        ];
    }

    /**
     * Resolve language codes to ids and the code-shaped id-reference
     * settings to local ids; failed rows are recorded on the result.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function resolveItem(array $payload, ImportResult $result, string $label): ?array
    {
        $name = $payload[SettingSchema::NAME];
        $item = [
            SettingSchema::NAME => $name,
            SettingSchema::VALUE => $payload[SettingSchema::VALUE],
        ];

        if ($name === SettingNameEnum::LANGUAGE_ID->value) {
            $languageId = $this->coreGateway->getLanguageIdByCode($payload[SettingSchema::VALUE]);
            if ($languageId === null) {
                $result->recordFailed($label.': unresolved_language:'.$payload[SettingSchema::VALUE]);

                return null;
            }
            $item[SettingSchema::VALUE] = (string) $languageId;
        }

        if ($name === SettingNameEnum::CURRENCY_ID->value) {
            $currency = $this->coreGateway->getActiveCurrencyByCode($payload[SettingSchema::VALUE]);
            if ($currency === null) {
                $result->recordFailed($label.': unresolved_currency:'.$payload[SettingSchema::VALUE]);

                return null;
            }
            $item[SettingSchema::VALUE] = (string) $currency->id;
        }

        $translations = [];
        foreach ($payload[SettingSchema::RES_TRANSLATIONS] ?? [] as $translation) {
            $languageId = $this->coreGateway->getLanguageIdByCode($translation[TranslationSchema::LANGUAGE]);
            if ($languageId === null) {
                $result->recordFailed($label.': unresolved_language:'.$translation[TranslationSchema::LANGUAGE]);

                return null;
            }
            $translations[] = [
                TranslationSchema::LANGUAGE_ID => $languageId,
                TranslationSchema::VALUE => $translation[TranslationSchema::VALUE],
            ];
        }
        $item[SettingSchema::RES_TRANSLATIONS] = $translations;

        return $item;
    }
}
