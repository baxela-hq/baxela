<?php

namespace Modules\Shipping\Http\Requests\Admin\Method;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;
use Modules\Shipping\Schemas\Method\MethodSchema;
use Modules\Shipping\Schemas\Method\MethodTranslationSchema as MTSchema;

class MethodRequest extends FormRequest
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
            MethodSchema::CODE => ['required', 'string', 'max:255',
                Rule::unique(MethodSchema::TABLE, MethodSchema::CODE)->ignore($id)],

            MethodSchema::IS_ACTIVE => ['required', 'boolean'],
            MethodSchema::POSITION => ['nullable', 'numeric', 'max:255'],

            MethodSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            MethodSchema::RES_TRANSLATIONS.'.*.'.MTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($this->languageMap))],
            MethodSchema::RES_TRANSLATIONS.'.*.'.MTSchema::LANGUAGE_ID => ['required', 'integer'],
            MethodSchema::RES_TRANSLATIONS.'.*.'.MTSchema::NAME => ['required', 'string', 'max:255'],
            MethodSchema::RES_TRANSLATIONS.'.*.'.MTSchema::DESCRIPTION => ['nullable', 'string'],
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
