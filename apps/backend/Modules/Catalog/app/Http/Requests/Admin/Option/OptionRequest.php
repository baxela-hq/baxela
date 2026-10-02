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

        $rules = self::rulesFor($this->languageMap);
        $rules[OptionSchema::RES_TRANSLATIONS.'.*.'.OTSchema::SLUG][] =
            new LanguageUniquePair(OTSchema::TABLE, OTSchema::SLUG, $this->languageMap, $id);

        return $rules;
    }

    /**
     * Payload shape rules, free of request context and DB-row checks —
     * reused verbatim by the catalog module data importer.
     *
     * @param  array<string, int>  $languageMap  code => id of the active languages
     * @return array<string, array<int, string>>
     */
    public static function rulesFor(array $languageMap): array
    {
        return [
            OptionSchema::POSITION => ['nullable', 'numeric', 'max:255'],

            OptionSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            OptionSchema::RES_TRANSLATIONS.'.*.'.OTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($languageMap))],
            OptionSchema::RES_TRANSLATIONS.'.*.'.OTSchema::LANGUAGE_ID => ['required', 'integer'],
            OptionSchema::RES_TRANSLATIONS.'.*.'.OTSchema::TITLE => ['required', 'string', 'max:255'],
            OptionSchema::RES_TRANSLATIONS.'.*.'.OTSchema::SLUG => ['required', 'string', 'max:255'],
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
