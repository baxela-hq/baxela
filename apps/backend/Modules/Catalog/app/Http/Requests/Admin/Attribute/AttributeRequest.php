<?php

namespace Modules\Catalog\Http\Requests\Admin\Attribute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\Attribute\AttributeTranslationSchema as ATSchema;
use Modules\Catalog\Schemas\Attribute\AttributeTypeEnum;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;

class AttributeRequest extends FormRequest
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
            AttributeSchema::GROUP_ID => ['required', 'integer',
                Rule::exists(AttributeGroupSchema::TABLE, AttributeGroupSchema::ID)],
            AttributeSchema::CODE => ['required', 'string', 'max:255',
                Rule::unique(AttributeSchema::TABLE, AttributeSchema::CODE)->ignore($id)],
            AttributeSchema::DATA_TYPE => ['required', new Enum(AttributeTypeEnum::class)],
            AttributeSchema::IS_FILTERABLE => ['required', 'boolean'],
            AttributeSchema::POSITION => ['nullable', 'numeric', 'max:255'],

            AttributeSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            AttributeSchema::RES_TRANSLATIONS.'.*.'.ATSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($this->languageMap))],
            AttributeSchema::RES_TRANSLATIONS.'.*.'.ATSchema::LANGUAGE_ID => ['required', 'integer'],
            AttributeSchema::RES_TRANSLATIONS.'.*.'.ATSchema::TITLE => ['required', 'string', 'max:255'],
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
