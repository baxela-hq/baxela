<?php

namespace Modules\Core\Http\Requests\Admin\Language;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\Schemas\Language\LanguageSchema;

class UpdateLanguageRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            LanguageSchema::IS_ACTIVE => ['boolean'],
        ];
    }
}
