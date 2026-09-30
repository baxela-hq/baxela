<?php

namespace Modules\Contact\Http\Requests\Admin\NewsletterSubscriber;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberSchema;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberStatusEnum;

class UpdateNewsletterSubscriberStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            NewsletterSubscriberSchema::STATUS => ['required', 'string', new Enum(NewsletterSubscriberStatusEnum::class)],
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
