<?php

namespace Modules\Menu\Http\Requests\Admin\MenuLink;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkTargetEnum;
use Modules\Menu\Schemas\MenuLink\MenuLinkTranslationSchema as MLTSchema;

class MenuLinkRequest extends FormRequest
{
    use ResolvesLanguagesTrait;

    protected function prepareForValidation(): void
    {
        $this->resolveLanguages();
    }

    public function rules(): array
    {
        $menuId = $this->route('id');
        $linkId = $this->route('linkId');

        return [
            MenuLinkSchema::PARENT_ID => ['nullable', 'integer',
                Rule::exists(MenuLinkSchema::TABLE, MenuLinkSchema::ID)
                    ->where(MenuLinkSchema::MENU_ID, $menuId)],

            MenuLinkSchema::URL => ['required', 'string', 'max:255'],
            MenuLinkSchema::TARGET => ['required', 'string', new Enum(MenuLinkTargetEnum::class)],
            MenuLinkSchema::POSITION => ['nullable', 'integer', 'max:255'],

            MenuLinkSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            MenuLinkSchema::RES_TRANSLATIONS.'.*.'.MLTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($this->languageMap))],
            MenuLinkSchema::RES_TRANSLATIONS.'.*.'.MLTSchema::LANGUAGE_ID => ['required', 'integer'],
            MenuLinkSchema::RES_TRANSLATIONS.'.*.'.MLTSchema::TITLE => ['required', 'string', 'max:255'],
            MenuLinkSchema::RES_TRANSLATIONS.'.*.'.MLTSchema::DESCRIPTION => ['nullable', 'string'],
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
