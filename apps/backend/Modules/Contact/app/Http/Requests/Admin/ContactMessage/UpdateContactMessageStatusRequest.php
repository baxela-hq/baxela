<?php

namespace Modules\Contact\Http\Requests\Admin\ContactMessage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Contact\Schemas\ContactMessage\ContactMessageSchema;
use Modules\Contact\Schemas\ContactMessage\ContactMessageStatusEnum;

class UpdateContactMessageStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            ContactMessageSchema::STATUS => ['required', 'string', new Enum(ContactMessageStatusEnum::class)],
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
