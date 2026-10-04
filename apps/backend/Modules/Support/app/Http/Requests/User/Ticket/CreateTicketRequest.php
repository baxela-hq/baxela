<?php

namespace Modules\Support\Http\Requests\User\Ticket;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;

class CreateTicketRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            TicketSchema::SUBJECT => ['required', 'string', 'max:255'],
            TicketMessageSchema::BODY => ['required', 'string', 'max:5000'],
            TicketSchema::ORDER_CODE => ['nullable', 'string', 'max:64'],
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
