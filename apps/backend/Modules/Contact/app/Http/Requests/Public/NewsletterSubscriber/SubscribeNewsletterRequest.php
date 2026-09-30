<?php

namespace Modules\Contact\Http\Requests\Public\NewsletterSubscriber;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberSchema;

class SubscribeNewsletterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            NewsletterSubscriberSchema::EMAIL => ['required', 'string', 'email', 'max:255'],
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
