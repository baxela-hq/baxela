<?php

namespace Modules\Catalog\Http\Requests\Admin\AttributeTemplate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateGroupSchema;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateSchema;

class AttributeTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            AttributeTemplateSchema::TITLE => ['required', 'string', 'max:255'],
            AttributeTemplateSchema::DESCRIPTION => ['nullable', 'string'],
            AttributeTemplateSchema::IS_ACTIVE => ['required', 'boolean'],
            AttributeTemplateSchema::POSITION => ['nullable', 'numeric', 'max:255'],

            AttributeTemplateGroupSchema::REQ_GROUP_IDS => ['nullable', 'array'],
            AttributeTemplateGroupSchema::REQ_GROUP_IDS.'.*' => ['integer',
                Rule::exists(AttributeGroupSchema::TABLE, AttributeGroupSchema::ID)],
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
