<?php

namespace Modules\Contact\Http\Requests\Public\ContactMessage;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Contact\Schemas\ContactMessage\ContactMessageSchema;

class SubmitContactMessageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            ContactMessageSchema::NAME => ['required', 'string', 'max:255'],
            ContactMessageSchema::EMAIL => ['required', 'string', 'email', 'max:255'],
            ContactMessageSchema::PHONE => ['nullable', 'string', 'max:30'],
            ContactMessageSchema::SUBJECT => ['required', 'string', 'max:255'],
            ContactMessageSchema::CONTENT => ['required', 'string', 'max:5000'],
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
