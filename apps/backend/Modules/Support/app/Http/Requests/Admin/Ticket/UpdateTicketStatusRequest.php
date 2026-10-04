<?php

namespace Modules\Support\Http\Requests\Admin\Ticket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Schemas\Ticket\TicketStatusEnum;

class UpdateTicketStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            TicketSchema::STATUS => ['required', 'string', new Enum(TicketStatusEnum::class)],
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
