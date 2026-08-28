<?php

namespace Modules\Catalog\Http\Requests\Admin\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\Category\CategoryAttributeSchema;
use Modules\Catalog\Schemas\Category\CategorySchema as CSchema;
use Modules\Catalog\Schemas\Category\CategoryTranslationSchema as CTSchema;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;
use Modules\Core\Rules\LanguageUniquePair;

class CategoryRequest extends FormRequest
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
            CSchema::PARENT_ID => ['nullable', 'integer', Rule::exists(CSchema::TABLE, CSchema::ID)],
            CSchema::POSITION => ['nullable', 'numeric', 'max:255'],

            CategoryAttributeSchema::REQ_ATTRIBUTE_IDS => ['nullable', 'array'],
            CategoryAttributeSchema::REQ_ATTRIBUTE_IDS.'.*' => ['integer',
                Rule::exists(AttributeSchema::TABLE, AttributeSchema::ID)],

            CSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            CSchema::RES_TRANSLATIONS.'.*.'.CTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($this->languageMap))],
            CSchema::RES_TRANSLATIONS.'.*.'.CTSchema::LANGUAGE_ID => ['required', 'integer'],
            CSchema::RES_TRANSLATIONS.'.*.'.CTSchema::TITLE => ['required', 'string', 'max:255'],
            CSchema::RES_TRANSLATIONS.'.*.'.CTSchema::SLUG => ['required', 'string',
                new LanguageUniquePair(CTSchema::TABLE, CTSchema::SLUG, $this->languageMap, $id)],
            CSchema::RES_TRANSLATIONS.'.*.'.CTSchema::DESCRIPTION => ['nullable', 'string', 'max:255'],
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
