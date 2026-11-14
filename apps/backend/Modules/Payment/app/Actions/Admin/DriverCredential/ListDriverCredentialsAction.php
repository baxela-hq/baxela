<?php

namespace Modules\Payment\Actions\Admin\DriverCredential;

use Modules\Core\Contracts\Gateways\Payment\ConfigurableDriver;
use Modules\Payment\Gateways\PaymentDriverManager;
use Modules\Payment\Support\DriverCredentialState;

/**
 * The admin credentials surface (marketplace P5.2 D5): per registered
 * configurable driver, the resolution state of every declared key —
 * never the values themselves.
 *
 * @return list<array<string, mixed>>
 */
class ListDriverCredentialsAction
{
    public function __construct(
        protected PaymentDriverManager $manager,
        protected DriverCredentialState $state,
    ) {}

    /** @return list<array<string, mixed>> */
    public function handle(): array
    {
        $states = [];

        foreach (array_keys((array) config('payment.drivers')) as $name) {
            $driver = $this->manager->forName($name);

            if (! $driver instanceof ConfigurableDriver) {
                continue;
            }

            $states[] = $this->state->build($name, $driver);
        }

        return $states;
    }
}
