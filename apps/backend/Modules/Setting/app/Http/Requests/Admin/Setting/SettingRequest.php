<?php

namespace Modules\Setting\Http\Requests\Admin\Setting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Schemas\Translation\TranslationSchema as TSchema;

class SettingRequest extends FormRequest
{
    use ResolvesLanguagesTrait;

    protected function prepareForValidation(): void
    {
        $items = collect($this->input());

        $codes = $items
            ->flatMap(fn ($setting) => $setting[SettingSchema::RES_TRANSLATIONS] ?? [])
            ->pluck(TSchema::LANGUAGE)
            ->unique()
            ->all();

        if (empty($codes)) {
            return;
        }

        $this->languageMap = Language::query()
            ->whereIn(LanguageSchema::CODE, $codes)
            ->pluck(LanguageSchema::ID, LanguageSchema::CODE)
            ->toArray();

        if (empty($this->languageMap)) {
            return;
        }

        $settings = $items->map(function ($setting) {
            if (! empty($setting[SettingSchema::RES_TRANSLATIONS])) {
                $setting[SettingSchema::RES_TRANSLATIONS] = collect($setting[SettingSchema::RES_TRANSLATIONS])
                    ->map(function ($translation) {
                        $translation[TSchema::LANGUAGE_ID] = $this->languageMap[$translation[TSchema::LANGUAGE]] ?? null;

                        return $translation;
                    })->all();
            }

            return $setting;
        })->all();

        $this->replace($settings);
    }

    public function rules(): array
    {
        $rules = [
            '*.'.SettingSchema::NAME => ['required', 'string', new Enum(SettingNameEnum::class)],
            '*.'.SettingSchema::RES_TRANSLATIONS => ['nullable', 'array'],
            '*.'.SettingSchema::RES_TRANSLATIONS.'.*.'.TSchema::LANGUAGE => ['required_with:*.'.SettingSchema::RES_TRANSLATIONS, 'string',
                Rule::in(array_keys($this->languageMap))],
            '*.'.SettingSchema::RES_TRANSLATIONS.'.*.'.TSchema::LANGUAGE_ID => ['required_with:*.'.SettingSchema::RES_TRANSLATIONS, 'integer'],
            '*.'.SettingSchema::RES_TRANSLATIONS.'.*.'.TSchema::VALUE => ['required_with:*.'.SettingSchema::RES_TRANSLATIONS, 'string'],
        ];

        // The FX credential is the one nullable value (review F12):
        // explicit null clears it, "" keeps it, "********" is the masked
        // placeholder and is never an acceptable submission. Every other
        // setting keeps required|string.
        foreach (array_keys($this->input()) as $index) {
            $name = $this->input("$index.".SettingSchema::NAME);

            $rules["$index.".SettingSchema::VALUE] = array_merge(
                $name === SettingNameEnum::FX_API_KEY->value
                    ? ['nullable', 'string', 'max:255']
                    : ['required', 'string'],
                [function (string $attribute, mixed $value, \Closure $fail) {
                    $this->validateValue($attribute, $value, $fail);
                }],
            );
        }

        return $rules;
    }

    /**
     * Per-name value constraints (keyed off the sibling name input).
     */
    protected function validateValue(string $attribute, mixed $value, \Closure $fail): void
    {
        $name = request()->input(str_replace('.'.SettingSchema::VALUE, '.'.SettingSchema::NAME, $attribute));

        if ($name === SettingNameEnum::FX_API_KEY->value) {
            if ($value === Setting::MASKED_VALUE) {
                $fail('The masked placeholder is not a valid API key — submit a new key, empty to keep, or clear the field.');
            }

            return;
        }

        if ($name === SettingNameEnum::FX_AUTO_SYNC_ENABLED->value
            && ! in_array($value, ['0', '1'], true)) {
            $fail('The FX auto-sync toggle must be 0 or 1.');
        }

        if ($name === SettingNameEnum::FX_PROVIDER->value
            && ! array_key_exists((string) $value, (array) config('core.fx.providers', []))) {
            $fail('The FX provider is not registered.');
        }

        if ($name === SettingNameEnum::FX_SYNC_INTERVAL_HOURS->value
            && (! is_numeric($value) || (int) $value < 1 || (int) $value > 8760)) {
            $fail('The FX sync interval must be between 1 and 8760 hours.');
        }

        if ($name === SettingNameEnum::FX_SYNC_STATUS->value) {
            // Machine-managed by the sync engine (review F12) — the
            // endpoint never accepts writes for it.
            $fail('The FX sync status is managed by the system and cannot be edited.');
        }

        if ($name === SettingNameEnum::TIMEZONE->value
            && ! in_array($value, \DateTimeZone::listIdentifiers(), true)) {
            $fail('The timezone must be a valid timezone identifier.');
        }

        if ($name === SettingNameEnum::PRICES_INCLUDE_TAX->value
            && ! in_array($value, ['0', '1'], true)) {
            $fail('The pricing mode must be 0 or 1.');
        }
    }

    public function authorize(): bool
    {
        return true;
    }
}
