<?php

namespace Modules\Payment\Http\Requests\Admin\DriverCredential;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Core\Contracts\Gateways\Payment\ConfigurableDriver;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\PaymentDriverManager;

/**
 * PATCH semantics (marketplace P5.2 F3): the body is a {key: value} map
 * of credential upserts. Everything is validated before the action runs
 * — non-empty strings only (null means "use DELETE"), bounded length,
 * declared keys only, at least one credential. Error messages name keys,
 * never values.
 */
class UpdateDriverCredentialsRequest extends FormRequest
{
    public function __construct(
        protected PaymentDriverManager $manager,
    ) {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The payload is a dynamic {key: value} map — structural checks run
     * in withValidator() against the driver's declared credentialKeys().
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $credentials = $this->json()->all();

            if ($credentials === []) {
                $validator->errors()->add('credentials', 'Provide at least one credential key and value.');

                return;
            }

            try {
                $driver = $this->manager->forName($this->route('driver'));
            } catch (PaymentException) {
                // The action surfaces the domain error for an unknown
                // driver; the request has nothing structural to say.
                return;
            }

            if (! $driver instanceof ConfigurableDriver) {
                return;
            }

            $declared = $driver->credentialKeys();

            foreach ($credentials as $key => $value) {
                if (! in_array($key, $declared, true)) {
                    $validator->errors()->add('credentials', "The key [{$key}] is not declared by this driver.");

                    continue;
                }

                if (! is_string($value)) {
                    $validator->errors()->add('credentials', "The credential [{$key}] must be a string.");

                    continue;
                }

                if (trim($value) === '' || $value === '0') {
                    $validator->errors()->add('credentials', "The credential [{$key}] must be a usable non-empty string. Use DELETE to remove it.");

                    continue;
                }

                if (strlen($value) > 2048) {
                    $validator->errors()->add('credentials', "The credential [{$key}] must not exceed 2048 characters.");
                }
            }
        });
    }

    /**
     * The validated credential map — already structurally checked, so
     * the action can treat it as trusted input.
     *
     * @return array<string, string>
     */
    public function credentials(): array
    {
        return $this->json()->all();
    }
}
