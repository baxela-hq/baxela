<?php

namespace Modules\Catalog\Http\Requests\Admin\OptionValue;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema as OVSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema as OVTSchema;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;
use Modules\Core\Rules\LanguageUniquePair;

class OptionValueRequest extends FormRequest
{
    use ResolvesLanguagesTrait;

    protected function prepareForValidation(): void
    {
        $this->resolveLanguages();
    }

    public function rules(): array
    {
        $id = $this->route('valueId');

        $rules = self::rulesFor($this->languageMap);
        $rules[OVSchema::RES_TRANSLATIONS.'.*.'.OVTSchema::SLUG][] =
            new LanguageUniquePair(OVTSchema::TABLE, OVTSchema::SLUG, $this->languageMap, $id);

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
            OVSchema::POSITION => ['nullable', 'numeric', 'max:255'],

            OVSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            OVSchema::RES_TRANSLATIONS.'.*.'.OVTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($languageMap))],
            OVSchema::RES_TRANSLATIONS.'.*.'.OVTSchema::LANGUAGE_ID => ['required', 'integer'],
            OVSchema::RES_TRANSLATIONS.'.*.'.OVTSchema::TITLE => ['required', 'string', 'max:255'],
            OVSchema::RES_TRANSLATIONS.'.*.'.OVTSchema::SLUG => ['required', 'string', 'max:255'],
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
