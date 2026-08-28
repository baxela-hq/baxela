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
        if (! $this->exists('*.translations')) {
            return;
        }

        $codes = collect($this->input('*.translations'))
            ->filter()
            ->flatten(1)
            ->pluck('language')
            ->unique()
            ->all();

        $this->languageMap = Language::query()
            ->whereIn(LanguageSchema::CODE, $codes)
            ->pluck(LanguageSchema::ID, LanguageSchema::CODE)
            ->toArray();

        if (empty($this->languageMap)) {
            return;
        }

        $settings = collect($this->input())->map(function ($setting) {
            if (isset($setting['translations']) && is_array($setting['translations'])) {
                $setting['translations'] = collect($setting['translations'])->map(function ($translation) {
                    $translation['language_id'] = $this->languageMap[$translation['language']] ?? null;

                    return $translation;
                })->all();
            }

            return $setting;
        })->all();

        $this->merge(['*' => $settings]);
    }

    public function rules(): array
    {
        return [
            '*.'.SettingSchema::NAME => ['required', 'string', new Enum(SettingNameEnum::class)],
            '*.'.SettingSchema::VALUE => ['required', 'string'],
            '*.'.SettingSchema::RES_TRANSLATIONS => ['nullable', 'array'],
            '*.'.SettingSchema::RES_TRANSLATIONS.'.*.'.TSchema::LANGUAGE => ['required_with:*.'.SettingSchema::RES_TRANSLATIONS, 'string', 'distinct', 'size:2',
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
