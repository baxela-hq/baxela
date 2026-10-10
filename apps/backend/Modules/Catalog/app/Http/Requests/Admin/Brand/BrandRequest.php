<?php

namespace Modules\Catalog\Http\Requests\Admin\Brand;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Schemas\Brand\BrandSchema as BSchema;
use Modules\Catalog\Schemas\Brand\BrandTranslationSchema as BTSchema;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;
use Modules\Core\Rules\LanguageUniquePair;

class BrandRequest extends FormRequest
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

        $rules[BSchema::RES_TRANSLATIONS.'.*.'.BTSchema::SLUG][] =
            new LanguageUniquePair(BTSchema::TABLE, BTSchema::SLUG, $this->languageMap, $id);

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
            BSchema::POSITION => ['nullable', 'numeric', 'max:255'],
            BSchema::IMAGE_MEDIA_ID => ['nullable', 'integer'],
            BSchema::IMAGE_URL => ['nullable', 'string', 'max:500'],

            BSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            BSchema::RES_TRANSLATIONS.'.*.'.BTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($languageMap))],
            BSchema::RES_TRANSLATIONS.'.*.'.BTSchema::LANGUAGE_ID => ['required', 'integer'],
            BSchema::RES_TRANSLATIONS.'.*.'.BTSchema::TITLE => ['required', 'string', 'max:255'],
            BSchema::RES_TRANSLATIONS.'.*.'.BTSchema::SLUG => ['required', 'string', 'max:255'],
            BSchema::RES_TRANSLATIONS.'.*.'.BTSchema::DESCRIPTION => ['nullable', 'string', 'max:255'],
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
