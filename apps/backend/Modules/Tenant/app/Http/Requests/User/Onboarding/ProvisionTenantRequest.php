<?php

namespace Modules\Tenant\Http\Requests\User\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tenant\Schemas\Tenant\TenantSchema;

/**
 * Self-service onboarding (multi-tenancy §9.7): the slug becomes the
 * store's `<slug>` subdomain, so it is a DNS label and unique.
 */
class ProvisionTenantRequest extends FormRequest
{
    /** @return array<string, array<int, string|Rule>> */
    public function rules(): array
    {
        return [
            TenantSchema::NAME => ['required', 'string', 'max:255'],
            TenantSchema::SLUG => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/',
                Rule::unique(TenantSchema::TABLE, TenantSchema::SLUG),
            ],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Any authenticated global user may provision a store; no existing
        // tenant or membership is required (§3.1, the onboarding audience).
        return $this->user() !== null;
    }
}
