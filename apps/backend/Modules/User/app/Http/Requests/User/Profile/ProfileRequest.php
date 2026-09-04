<?php

namespace Modules\User\Http\Requests\User\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\User\Schemas\Profile\GenderEnum;
use Modules\User\Schemas\Profile\ProfileSchema;

class ProfileRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            ProfileSchema::FULL_NAME => ['required', 'string', 'max:255'],
            ProfileSchema::DISPLAY_NAME => ['nullable', 'string', 'max:255'],
            ProfileSchema::BIO => ['nullable', 'string'],
            ProfileSchema::AVATAR => ['nullable', 'string', 'max:255'],
            ProfileSchema::GENDER => ['nullable', new Enum(GenderEnum::class)],
            ProfileSchema::DATE_OF_BIRTH => ['nullable', 'date', 'before:today'],
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
