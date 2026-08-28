<?php

namespace Modules\Content\Http\Requests\Admin\Page;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Content\Schemas\Page\PageSchema as PSchema;
use Modules\Content\Schemas\Page\PageStatusEnum;
use Modules\Content\Schemas\Page\PageTranslationSchema as PTSchema;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;
use Modules\Core\Rules\LanguageUniquePair;

class PageRequest extends FormRequest
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
        $id = $this->route('id');

        return [
            PSchema::STATUS => ['required', new Enum(PageStatusEnum::class)],

            PSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            PSchema::RES_TRANSLATIONS.'.*.'.PTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($this->languageMap))],
            PSchema::RES_TRANSLATIONS.'.*.'.PTSchema::LANGUAGE_ID => ['required', 'integer'],
            PSchema::RES_TRANSLATIONS.'.*.'.PTSchema::TITLE => ['required', 'string', 'max:255'],
            PSchema::RES_TRANSLATIONS.'.*.'.PTSchema::SLUG => ['required', 'string',
                new LanguageUniquePair(PTSchema::TABLE, PTSchema::SLUG, $this->languageMap, $id)],
            PSchema::RES_TRANSLATIONS.'.*.'.PTSchema::CONTENT => ['required', 'string'],
            PSchema::RES_TRANSLATIONS.'.*.'.PTSchema::DESCRIPTION => ['nullable', 'string', 'max:255'],
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
