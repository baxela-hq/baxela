<?php

namespace Modules\Support\Http\Requests\Admin\TicketMessage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;

class CreateTicketMessageRequest extends FormRequest
{
    /** @return array<string, array<int, string|Enum>> */
    public function rules(): array
    {
        return [
            TicketMessageSchema::BODY => ['required', 'string', 'max:5000'],
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
