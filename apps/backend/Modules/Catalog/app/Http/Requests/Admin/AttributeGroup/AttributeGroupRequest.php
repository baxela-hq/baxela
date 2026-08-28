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
        return [
            AttributeGroupSchema::POSITION => ['nullable', 'numeric', 'max:255'],

            AttributeGroupSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            AttributeGroupSchema::RES_TRANSLATIONS.'.*.'.AGTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($this->languageMap))],
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
