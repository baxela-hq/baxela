<?php

namespace Modules\Catalog\Http\Requests\Admin\Option;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Schemas\Option\OptionSchema;
use Modules\Catalog\Schemas\Option\OptionTranslationSchema as OTSchema;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;
use Modules\Core\Rules\LanguageUniquePair;

class OptionRequest extends FormRequest
{
    use ResolvesLanguagesTrait;

    protected function prepareForValidation(): void
    {
        $this->resolveLanguages();
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            OptionSchema::POSITION => ['nullable', 'numeric', 'max:255'],

            OptionSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            OptionSchema::RES_TRANSLATIONS.'.*.'.OTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($this->languageMap))],
            OptionSchema::RES_TRANSLATIONS.'.*.'.OTSchema::LANGUAGE_ID => ['required', 'integer'],
            OptionSchema::RES_TRANSLATIONS.'.*.'.OTSchema::TITLE => ['required', 'string', 'max:255'],
            OptionSchema::RES_TRANSLATIONS.'.*.'.OTSchema::SLUG => ['required', 'string', 'max:255',
                new LanguageUniquePair(OTSchema::TABLE, OTSchema::SLUG, $this->languageMap, $id)],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
