<?php

namespace Modules\Auth\Http\Requests\Admin\Token;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Machine token minting (gap-C2 D14): abilities are explicit and
 * default-deny — the allow-list is the contract; wildcard tokens are
 * rejected at mint time.
 */
class MachineTokenRequest extends FormRequest
{
    public const string ABILITY_ORDERS_READ = 'orders.read';

    public const string ABILITY_PRODUCTS_READ = 'products.read';

    public const array ALLOWED_ABILITIES = [
        self::ABILITY_ORDERS_READ,
        self::ABILITY_PRODUCTS_READ,
    ];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // The designated machine/service user (integration identity,
            // never an arbitrary admin).
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(self::ALLOWED_ABILITIES)],
            'expires_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
