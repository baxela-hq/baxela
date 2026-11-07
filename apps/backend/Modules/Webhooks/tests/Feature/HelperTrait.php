<?php

namespace Modules\Webhooks\Tests\Feature;

use Modules\Auth\Models\User;
use Modules\Webhooks\Models\WebhookEndpoint;

/**
 * @used-by Modules\Webhooks\Tests\Feature\Admin\WebhookEndpointTest
 * @used-by Modules\Webhooks\Tests\Feature\Admin\WebhookDeliveryTest
 * @used-by Modules\Webhooks\Tests\Feature\Admin\WebhookOutboxTest
 */
trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/webhooks'.$endpoint;
    }

    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /**
     * A public-URL endpoint subscribed to the given event types; the
     * plaintext secret is returned alongside the row.
     *
     * @param  list<string>  $events
     * @return array{0: WebhookEndpoint, 1: string}
     */
    public function webhookEndpoint(array $events = ['order.paid'], array $overrides = []): array
    {
        $secret = 'test-secret-'.bin2hex(random_bytes(8));

        $endpoint = WebhookEndpoint::query()->create(array_merge([
            'name' => 'Test endpoint',
            'url' => 'https://93.184.216.34/receive',
            'secret' => $secret,
            'events' => $events,
            'is_active' => true,
            'is_archived' => false,
            'consecutive_failures' => 0,
            'created_by' => 1,
        ], $overrides));

        return [$endpoint, $secret];
    }
}
