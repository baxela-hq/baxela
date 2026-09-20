<?php

namespace Modules\Notification\Http\Requests\Admin\PushSubscription;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Notification\Schemas\PushSubscription\PushSubscriptionSchema;

class PushSubscriptionRequest extends FormRequest
{
    /**
     * Push-subscription shape shared by the upsert and delete endpoints:
     * the browser PushSubscription JSON (endpoint + keys) plus capture
     * context. Delete sends only the endpoint.
     */
    public function rules(): array
    {
        return [
            PushSubscriptionSchema::ENDPOINT => ['required', 'url', 'max:500'],
            PushSubscriptionSchema::KEYS => ['nullable', 'array'],
            PushSubscriptionSchema::KEYS.'.'.PushSubscriptionSchema::P256DH => ['nullable', 'string', 'max:255'],
            PushSubscriptionSchema::KEYS.'.'.PushSubscriptionSchema::AUTH => ['nullable', 'string', 'max:255'],
            PushSubscriptionSchema::USER_AGENT => ['nullable', 'string', 'max:500'],
            PushSubscriptionSchema::LOCALE => ['nullable', 'string', 'max:12'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
