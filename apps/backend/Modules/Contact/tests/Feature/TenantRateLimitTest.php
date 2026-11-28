<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Modules\Contact\Models\ContactMessage;
use Modules\Contact\Tests\Feature\HelperTrait;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantDomain;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * @return array<string, mixed>
 */
function rateLimitTenant(string $slug, string $domain): array
{
    $tenant = Tenant::query()->create([
        TenantSchema::NAME => ucfirst($slug).' Store',
        TenantSchema::SLUG => $slug,
        TenantSchema::EMAIL => $slug.'@example.test',
        TenantSchema::STATUS => TenantSchema::STATUS_ACTIVE,
    ]);

    TenantDomain::query()->create([
        TenantDomainSchema::TENANT_ID => $tenant->getKey(),
        TenantDomainSchema::DOMAIN => $domain,
        TenantDomainSchema::STATUS => TenantDomainSchema::STATUS_VERIFIED,
        TenantDomainSchema::IS_PRIMARY => true,
        TenantDomainSchema::VERIFIED_AT => now(),
    ]);

    return ['tenant' => $tenant, 'domain' => $domain];
}

/**
 * @return array<string, string>
 */
function contactPayload(string $email): array
{
    return [
        'name' => 'Jane Doe',
        'email' => $email,
        'subject' => 'Question',
        'content' => 'Do you ship internationally?',
    ];
}

it('does not let one store saturate another store\'s contact-submit quota', function () {
    Config::set('contact.rate_limit.submit', 1);

    $storeA = rateLimitTenant('limit-a', 'limit-a.example.test');
    $storeB = rateLimitTenant('limit-b', 'limit-b.example.test');

    // Same endpoint, same IP, same limiter name — only the resolved store
    // differs. X-Tenant-Host is how a storefront forwards its host to the
    // central API (§7).
    $this->withHeaders(['X-Tenant-Host' => $storeA['domain']])
        ->postJson($this->baseUrl('/public/messages'), contactPayload('a-one@example.test'))
        ->assertCreated();
    $this->withHeaders(['X-Tenant-Host' => $storeA['domain']])
        ->postJson($this->baseUrl('/public/messages'), contactPayload('a-two@example.test'))
        ->assertStatus(429);

    $this->withHeaders(['X-Tenant-Host' => $storeB['domain']])
        ->postJson($this->baseUrl('/public/messages'), contactPayload('b-one@example.test'))
        ->assertCreated();

    expect(ContactMessage::query()->where('email', 'b-one@example.test')->exists())->toBeTrue();
});
