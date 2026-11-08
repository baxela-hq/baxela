<?php

namespace Modules\User\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Modules\Core\Contracts\Gateways\Tax\TaxGatewayInterface;

/**
 * VAT-identifier format check against the submitted country (gap-C1c):
 * countries without a rule PASS (stored without a validation claim);
 * a failing check never echoes the submitted value back (privacy —
 * VAT ids are identifier-grade data).
 */
readonly class VatIdRule implements ValidationRule
{
    public function __construct(private string $countryCode) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return; // emptiness is the nullable rule's concern
        }

        $plausible = app(TaxGatewayInterface::class)->validateVatId($this->countryCode, $value);

        if ($plausible === false) {
            $fail(__('user::errors.address.vat_id_invalid'));
        }
    }
}
