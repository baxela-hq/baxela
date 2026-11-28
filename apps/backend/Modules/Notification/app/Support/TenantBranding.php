<?php

namespace Modules\Notification\Support;

use Modules\Core\Support\Tenancy\TenantContext;

/**
 * Store identity injected into notification templates at RENDER time
 * (multi-tenancy §6.12): reading it here — rather than stamping it into a
 * queued payload — means a worker that serves store A and then store B
 * renders each notification with that store's own name and storefront.
 *
 * The name comes from the active tenant snapshot (the same object tenant
 * resolution and queue stamping use); the storefront URL comes from the
 * snapshot's verified domains. A tenant-neutral context (platform alerts)
 * falls back to the installation's configured identity — but never to a
 * previously resolved store.
 */
final class TenantBranding
{
    public function __construct(private readonly TenantContext $context) {}

    public function storeName(): string
    {
        $name = $this->context->tenant()?->name;

        return $name !== null && $name !== ''
            ? $name
            : (string) config('app.name', '');
    }

    public function storefrontUrl(): string
    {
        $tenant = $this->context->tenant();
        $domains = $tenant === null ? [] : $tenant->domains;
        $domain = $domains[0] ?? null;

        return $domain === null || $domain === ''
            ? rtrim((string) config('app.url', ''), '/')
            : 'https://'.$domain;
    }

    /**
     * Branding variables for every template engine. They are merged at
     * render time with engine precedence, so a listener cannot pin an old
     * store's name into a message that is later rendered for another one.
     *
     * @return array{store_name: string, storefront_url: string}
     */
    public function variables(): array
    {
        return [
            'store_name' => $this->storeName(),
            'storefront_url' => $this->storefrontUrl(),
        ];
    }
}
