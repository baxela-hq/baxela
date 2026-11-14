<?php

namespace Modules\Auth\Http\Requests\Public\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Schemas\User\UserSchema;

class GoogleSignInRequest extends FormRequest
{
    /**
     * The Google ID token is a bearer-grade credential: it must never be
     * echoed back by a validation failure or logged. `$dontFlash` covers
     * the validation-error boundary; log scrubbing is enforced by never
     * logging request payloads in the Google flow (invariant: the ID
     * token is never logged, persisted, or stored in error context).
     *
     * @var list<int|string>
     */
    protected $dontFlash = [
        'credential',
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // OIDC ID tokens are ~1-2 KB; the cap rejects garbage early.
            'credential' => ['required', 'string', 'min:16', 'max:4096'],
            UserSchema::DEVICE_NAME => ['nullable', 'string', 'max:100'],
            UserSchema::REMEMBER => ['nullable', 'boolean'],
        ];
    }
}
