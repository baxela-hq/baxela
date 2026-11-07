<?php

namespace Modules\Webhooks\Http\Requests\Admin\WebhookEndpoint;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Webhooks\Support\EventCatalog;

class WebhookEndpointRequest extends FormRequest
{
    /**
     * Fixed payload shape (repo convention — no `sometimes`): create and
     * update share it; the URL's SSRF validity is checked in the action
     * (parse-time) and re-validated at every delivery.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(array_keys(EventCatalog::types()))],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
