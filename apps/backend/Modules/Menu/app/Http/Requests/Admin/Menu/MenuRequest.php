<?php

namespace Modules\Menu\Http\Requests\Admin\Menu;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;
use Modules\Core\Rules\ScopedUnique;
use Modules\Menu\Schemas\Menu\MenuLocationEnum;
use Modules\Menu\Schemas\Menu\MenuSchema;
use Modules\Menu\Schemas\Menu\MenuTranslationSchema as MTSchema;

class MenuRequest extends FormRequest
{
    use ResolvesLanguagesTrait;

    protected function prepareForValidation(): void
    {
        $this->resolveLanguages();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            MenuSchema::LOCATION => ['required', 'string', new Enum(MenuLocationEnum::class),
                new ScopedUnique(MenuSchema::TABLE, MenuSchema::LOCATION, is_null($id) ? null : (int) $id)],

            MenuSchema::IS_ACTIVE => ['required', 'boolean'],

            MenuSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            MenuSchema::RES_TRANSLATIONS.'.*.'.MTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($this->languageMap))],
            MenuSchema::RES_TRANSLATIONS.'.*.'.MTSchema::LANGUAGE_ID => ['required', 'integer'],
            MenuSchema::RES_TRANSLATIONS.'.*.'.MTSchema::TITLE => ['required', 'string', 'max:255'],
            MenuSchema::RES_TRANSLATIONS.'.*.'.MTSchema::DESCRIPTION => ['nullable', 'string'],
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
