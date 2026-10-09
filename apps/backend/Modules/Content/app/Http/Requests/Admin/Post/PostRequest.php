<?php

namespace Modules\Content\Http\Requests\Admin\Post;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Content\Schemas\Post\PostImageCollectionEnum;
use Modules\Content\Schemas\Post\PostImageSchema as PISchema;
use Modules\Content\Schemas\Post\PostSchema as PSchema;
use Modules\Content\Schemas\Post\PostSeoTranslationSchema as PSTSchema;
use Modules\Content\Schemas\Post\PostStatusEnum;
use Modules\Content\Schemas\Post\PostTranslationSchema as PTSchema;
use Modules\Content\Schemas\PostCategory\PostCategorySchema as PCSchema;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;
use Modules\Core\Rules\LanguageUniquePair;

class PostRequest extends FormRequest
{
    use ResolvesLanguagesTrait;

    protected function prepareForValidation(): void
    {
        $this->resolveLanguages();
        $this->resolveLanguagesFor(PSchema::RES_SEO);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // null on create, the post id on update — /api/v1/content/admin/posts/{id}
        $id = $this->route('id');

        $rules = [
            PSchema::STATUS => ['required', new Enum(PostStatusEnum::class)],
            PSchema::PUBLISHED_AT => ['nullable', 'date'],

            PSchema::RES_CATEGORIES => ['nullable', 'array'],
            PSchema::RES_CATEGORIES.'.*' => ['integer'],

            // images
            PSchema::RES_IMAGES => ['nullable', 'array', 'min:1'],
            PSchema::RES_IMAGES.'.*.'.PISchema::POSITION => ['nullable', 'numeric', 'max:255'],
            PSchema::RES_IMAGES.'.*.'.PISchema::COLLECTION => ['nullable', new Enum(PostImageCollectionEnum::class)],
            PSchema::RES_IMAGES.'.*.'.PISchema::MEDIA_ID => ['required', 'integer'],
            PSchema::RES_IMAGES.'.*.'.PISchema::URL => ['required', 'string'],

            // seo
            PSchema::RES_SEO => ['nullable', 'array'],
            PSchema::RES_SEO.'.*.'.PSTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($this->languageMap))],
            PSchema::RES_SEO.'.*.'.PSTSchema::LANGUAGE_ID => ['required', 'integer'],
            PSchema::RES_SEO.'.*.'.PSTSchema::META_TITLE => ['nullable', 'string', 'max:255'],
            PSchema::RES_SEO.'.*.'.PSTSchema::META_DESCRIPTION => ['nullable', 'string', 'max:255'],
            PSchema::RES_SEO.'.*.'.PSTSchema::OPEN_GRAPH_TITLE => ['nullable', 'string', 'max:255'],
            PSchema::RES_SEO.'.*.'.PSTSchema::OPEN_GRAPH_DESCRIPTION => ['nullable', 'string', 'max:255'],

            PSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            PSchema::RES_TRANSLATIONS.'.*.'.PTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($this->languageMap))],
            PSchema::RES_TRANSLATIONS.'.*.'.PTSchema::LANGUAGE_ID => ['required', 'integer'],
            PSchema::RES_TRANSLATIONS.'.*.'.PTSchema::TITLE => ['required', 'string', 'max:255'],
            PSchema::RES_TRANSLATIONS.'.*.'.PTSchema::SLUG => ['required', 'string', 'max:255'],
            PSchema::RES_TRANSLATIONS.'.*.'.PTSchema::CONTENT => ['required', 'string'],
            PSchema::RES_TRANSLATIONS.'.*.'.PTSchema::DESCRIPTION => ['nullable', 'string', 'max:255'],
        ];

        $rules[PSchema::RES_CATEGORIES.'.*'][] = Rule::exists(PCSchema::TABLE, PCSchema::ID);
        $rules[PSchema::RES_TRANSLATIONS.'.*.'.PTSchema::SLUG][] =
            new LanguageUniquePair(PTSchema::TABLE, PTSchema::SLUG, $this->languageMap, $id);

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
