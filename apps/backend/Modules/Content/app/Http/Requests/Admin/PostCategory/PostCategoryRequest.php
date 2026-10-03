<?php

namespace Modules\Content\Http\Requests\Admin\PostCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Content\Schemas\PostCategory\PostCategorySchema as PCSchema;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema as PCTSchema;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;
use Modules\Core\Rules\LanguageUniquePair;

class PostCategoryRequest extends FormRequest
{
    use ResolvesLanguagesTrait;

    protected function prepareForValidation(): void
    {
        $this->resolveLanguages();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // null on create, the category id on update — /api/v1/content/admin/post-categories/{id}
        $id = $this->route('id');

        $rules = [
            PCSchema::PARENT_ID => ['nullable', 'integer'],
            PCSchema::POSITION => ['nullable', 'numeric', 'max:255'],

            PCSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            PCSchema::RES_TRANSLATIONS.'.*.'.PCTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($this->languageMap))],
            PCSchema::RES_TRANSLATIONS.'.*.'.PCTSchema::LANGUAGE_ID => ['required', 'integer'],
            PCSchema::RES_TRANSLATIONS.'.*.'.PCTSchema::TITLE => ['required', 'string', 'max:255'],
            PCSchema::RES_TRANSLATIONS.'.*.'.PCTSchema::SLUG => ['required', 'string', 'max:255'],
            PCSchema::RES_TRANSLATIONS.'.*.'.PCTSchema::DESCRIPTION => ['nullable', 'string', 'max:255'],
        ];

        $rules[PCSchema::PARENT_ID][] = Rule::exists(PCSchema::TABLE, PCSchema::ID);
        $rules[PCSchema::RES_TRANSLATIONS.'.*.'.PCTSchema::SLUG][] =
            new LanguageUniquePair(PCTSchema::TABLE, PCTSchema::SLUG, $this->languageMap, $id);

        return $rules;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
