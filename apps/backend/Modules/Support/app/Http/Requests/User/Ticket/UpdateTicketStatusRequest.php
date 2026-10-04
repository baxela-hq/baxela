<?php

namespace Modules\Support\Http\Requests\User\Ticket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Schemas\Ticket\TicketStatusEnum;

class UpdateTicketStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // customers may only close or reopen — never mark answered
            TicketSchema::STATUS => [
                'required',
                'string',
                Rule::in([TicketStatusEnum::OPEN->value, TicketStatusEnum::CLOSED->value]),
            ],
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
