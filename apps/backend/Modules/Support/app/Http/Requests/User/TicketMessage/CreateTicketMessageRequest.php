<?php

namespace Modules\Support\Http\Requests\User\TicketMessage;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;

class CreateTicketMessageRequest extends FormRequest
{
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
