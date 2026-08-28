<?php

namespace Modules\Setting\Http\Requests\Admin\Setting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Language\LanguageSchema;
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
        return [
            '*.'.SettingSchema::NAME => ['required', 'string', new Enum(SettingNameEnum::class)],
            '*.'.SettingSchema::VALUE => ['required', 'string'],
            '*.'.SettingSchema::RES_TRANSLATIONS => ['nullable', 'array'],
            '*.'.SettingSchema::RES_TRANSLATIONS.'.*.'.TSchema::LANGUAGE => ['required_with:*.'.SettingSchema::RES_TRANSLATIONS, 'string',
                Rule::in(array_keys($this->languageMap))],
            '*.'.SettingSchema::RES_TRANSLATIONS.'.*.'.TSchema::LANGUAGE_ID => ['required_with:*.'.SettingSchema::RES_TRANSLATIONS, 'integer'],
            '*.'.SettingSchema::RES_TRANSLATIONS.'.*.'.TSchema::VALUE => ['required_with:*.'.SettingSchema::RES_TRANSLATIONS, 'string'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
