<?php

namespace Modules\Catalog\Http\Requests\Admin\AttributeValue;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueTranslationSchema as AVTSchema;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;

class AttributeValueRequest extends FormRequest
{
    use ResolvesLanguagesTrait;

    protected function prepareForValidation(): void
    {
        $this->resolveLanguages();
    }

    public function rules(): array
    {
        return self::rulesFor($this->languageMap);
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
            AttributeValueSchema::POSITION => ['nullable', 'numeric', 'max:255'],

            AttributeValueSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            AttributeValueSchema::RES_TRANSLATIONS.'.*.'.AVTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($languageMap))],
            AttributeValueSchema::RES_TRANSLATIONS.'.*.'.AVTSchema::LANGUAGE_ID => ['required', 'integer'],
            AttributeValueSchema::RES_TRANSLATIONS.'.*.'.AVTSchema::TITLE => ['required', 'string', 'max:255'],
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
