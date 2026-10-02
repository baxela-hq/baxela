<?php

namespace Modules\Catalog\Http\Requests\Admin\AttributeGroup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupTranslationSchema as AGTSchema;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;

class AttributeGroupRequest extends FormRequest
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
            AttributeGroupSchema::POSITION => ['nullable', 'numeric', 'max:255'],

            AttributeGroupSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            AttributeGroupSchema::RES_TRANSLATIONS.'.*.'.AGTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($languageMap))],
            AttributeGroupSchema::RES_TRANSLATIONS.'.*.'.AGTSchema::LANGUAGE_ID => ['required', 'integer'],
            AttributeGroupSchema::RES_TRANSLATIONS.'.*.'.AGTSchema::TITLE => ['required', 'string', 'max:255'],
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
