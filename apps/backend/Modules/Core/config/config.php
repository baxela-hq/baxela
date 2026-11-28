<?php

use Modules\Core\Transfer\CoreTransfer;

return [
    'name' => 'Core',
    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    |
    | Enable or disable request/response logging globally.
    | Keep this disabled unless debugging: event payloads can carry live
    | credentials (recognized secrets are redacted, but any logging of
    | authentication flows is a leak surface).
    |
    */
    'log_requests' => env('CORE_LOG_REQUESTS', false),

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies (S.3)
    |--------------------------------------------------------------------------
    |
    | Comma-separated IPs/CIDRs allowed to send forwarding headers
    | (X-Forwarded-For / X-Forwarded-Proto only — see TrustProxies
    | middleware). Empty trusts nobody: forwarded headers are ignored
    | and $request->secure() reflects only real TLS. Deployments set
    | this to the compose `proxy` network's subnet — never `*`, which
    | on the shared compose network would trust every container peer.
    |
    */
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    /*
    |--------------------------------------------------------------------------
    | Tenancy (multi-tenancy §3.3)
    |--------------------------------------------------------------------------
    |
    | An unregistered host resolves to the default tenant ONLY when it
    | matches the fallback list. The default covers loopback names so
    | local development needs no tenant setup; production ships an EMPTY
    | list, so an unknown domain 404s instead of falling back.
    |
    */
    'tenancy' => [
        'fallback_hosts' => env('TENANCY_FALLBACK_HOSTS', 'localhost,127.0.0.1'),
        'fallback_tenant' => 'default',
        // Admin API hosts where host-based resolution is skipped and the
        // store comes from X-Tenant-Id / membership selection instead.
        'central_hosts' => env('TENANCY_CENTRAL_HOSTS', ''),

        // Routes allowed to carry the tenant-neutral `onboarding`
        // audience (§3.1). They skip tenant resolution entirely; anything
        // else using that audience fails closed.
        'onboarding_routes' => [
            'api.tenant.onboarding.tenants.store',
        ],

        // Platform-level routes: they carry no audience segment and serve
        // the installation, not a store. Tenant-neutral — they skip tenant
        // resolution and answer on any host.
        'platform_routes' => [
            'api.platform.me',
            // Broadcasting auth (the group prefix with an empty leaf name).
            'api.notification',
            'api.notification.webpush.vapid-public-key',
            'api.notification.telegram.bot-info',
        ],

        // Admin routes reserved for the platform super-admin: no
        // permission row is created for them, so they can never be
        // assigned to a role (§4.1). Tenant CRUD and domain
        // administration are platform operations (§6.5, Category 1);
        // membership management within the active tenant is a tenant
        // operation (Category 2) and carries real permission rows.
        'super_admin_only_routes' => [
            // Covers tenants.* and tenants.domains.*; memberships.* is a
            // Category-2 tenant operation and carries permission rows.
            'api.tenant.admin.tenants.',
        ],

        'domain' => [
            'cooling_off_days' => (int) env('TENANCY_DOMAIN_COOLING_OFF_DAYS', 7),
            'verification_ttl_hours' => (int) env('TENANCY_DOMAIN_VERIFICATION_TTL', 48),
        ],

        'onboarding' => [
            'max_tenants_per_user' => (int) env('TENANCY_MAX_TENANTS_PER_USER', 3),
            'subdomain_base' => env('TENANCY_SUBDOMAIN_BASE', 'localhost'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Tenancy audit pivot relations (multi-tenancy §4.3, layer 4)
        |--------------------------------------------------------------------------
        |
        | Direct relations are discovered from database foreign keys; the nine
        | tenant-less pivots cannot be discovered because ownership lives in
        | both parents, so they are declared here. A pivot row whose parents
        | belong to different stores is a violation.
        |
        */
        'audit_pivots' => [
            [
                'pivot' => 'catalog_attribute_template_groups',
                'left' => ['table' => 'catalog_attribute_templates', 'column' => 'template_id'],
                'right' => ['table' => 'catalog_attribute_groups', 'column' => 'group_id'],
            ],
            [
                'pivot' => 'catalog_category_attribute',
                'left' => ['table' => 'catalog_categories', 'column' => 'category_id'],
                'right' => ['table' => 'catalog_attributes', 'column' => 'attribute_id'],
            ],
            [
                'pivot' => 'catalog_category_product',
                'left' => ['table' => 'catalog_categories', 'column' => 'category_id'],
                'right' => ['table' => 'catalog_products', 'column' => 'product_id'],
            ],
            [
                'pivot' => 'catalog_variant_option_values',
                'left' => ['table' => 'catalog_variants', 'column' => 'variant_id'],
                'right' => ['table' => 'catalog_option_values', 'column' => 'option_value_id'],
            ],
            [
                'pivot' => 'content_post_category_post',
                'left' => ['table' => 'content_post_categories', 'column' => 'post_category_id'],
                'right' => ['table' => 'content_posts', 'column' => 'post_id'],
            ],
            [
                'pivot' => 'content_post_product',
                'left' => ['table' => 'content_posts', 'column' => 'post_id'],
                'right' => ['table' => 'catalog_products', 'column' => 'product_id'],
            ],
            [
                'pivot' => 'discount_promotion_categories',
                'left' => ['table' => 'discount_promotions', 'column' => 'promotion_id'],
                'right' => ['table' => 'catalog_categories', 'column' => 'category_id'],
            ],
            [
                'pivot' => 'discount_promotion_products',
                'left' => ['table' => 'discount_promotions', 'column' => 'promotion_id'],
                'right' => ['table' => 'catalog_products', 'column' => 'product_id'],
            ],
            [
                'pivot' => 'shipping_zone_countries',
                'left' => ['table' => 'shipping_zones', 'column' => 'zone_id'],
                // Countries are installation-level; ownership derives from
                // the zone alone, so this relation has no tenant side.
                'right' => null,
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Tenancy architecture allowlists (multi-tenancy §4.3, rows 48–50)
        |--------------------------------------------------------------------------
        |
        | Entries are keyed by source file, enclosing method and occurrence,
        | never by file alone: a newly added chain in an already-listed file
        | is a new site and must be registered deliberately. `guarded` sites
        | obtain their tenant id from TenantQueryScope / TenantWriteStamp;
        | `central` sites run inside a registered central operation.
        |
        */
        'query_sites' => [
            'Modules/Catalog/app/Actions/Admin/DataTransfer/ImportCatalogDataAction.php::checkSlugUniqueness::$table#1' => [
                'table' => '$table',
                'operation' => 'select',
                'justification' => 'central operation: transfer-import lookup remapped through the source installation (§4.3 layer 0)',
            ],
            'Modules/Catalog/app/Actions/Admin/DataTransfer/ImportCatalogDataAction.php::matchAttributeValue::catalog_attribute_value_translations#1' => [
                'table' => 'catalog_attribute_value_translations',
                'operation' => 'select',
                'justification' => 'central operation: transfer-import lookup remapped through the source installation (§4.3 layer 0)',
            ],
            'Modules/Catalog/app/Actions/Admin/DataTransfer/ImportCatalogDataAction.php::matchByTranslation::$table#1' => [
                'table' => '$table',
                'operation' => 'select',
                'justification' => 'central operation: transfer-import lookup remapped through the source installation (§4.3 layer 0)',
            ],
            'Modules/Catalog/app/Actions/Admin/DataTransfer/ImportCatalogDataAction.php::matchOptionValue::catalog_option_value_translations#1' => [
                'table' => 'catalog_option_value_translations',
                'operation' => 'select',
                'justification' => 'central operation: transfer-import lookup remapped through the source installation (§4.3 layer 0)',
            ],
            'Modules/Catalog/app/Search/SearchAdmissionOverride.php::openAdmissions::$admissions#1' => [
                'table' => '$admissions',
                'operation' => 'select',
                'justification' => 'central operation: installation-level search coordination (§4.3 layer 0)',
            ],
            'Modules/Catalog/app/Search/SearchWorkerHeartbeat.php::prune::$heartbeats#1' => [
                'table' => '$heartbeats',
                'operation' => 'select',
                'justification' => 'central operation: installation-level search coordination (§4.3 layer 0)',
            ],
            'Modules/Catalog/app/Support/AppliesProductPromotions.php::resolve::catalog_category_product#1' => [
                'table' => 'catalog_category_product',
                'operation' => 'select',
                'justification' => 'central operation: parent-scoped promotion/category read (§4.3 layer 0)',
            ],
            'Modules/Catalog/app/Support/ProductImport/ProductImportOptionResolver.php::uniqueTranslationSlug::$table#1' => [
                'table' => '$table',
                'operation' => 'select',
                'justification' => 'central operation: transfer-import lookup remapped through the source installation (§4.3 layer 0)',
            ],
            'Modules/Content/app/Transfer/ContentTransfer.php::checkSlugUniqueness::$translationTable#1' => [
                'table' => '$translationTable',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Content/app/Transfer/ContentTransfer.php::matchByDefaultSlug::$translationTable#1' => [
                'table' => '$translationTable',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Core/app/Rules/LanguageUniquePair.php::validate::$this->table#1' => [
                'table' => '$this->table',
                'operation' => 'select',
                'justification' => 'central operation: scoped validation rule supplies the tenant predicate (§4.3 layer 0)',
            ],
            'Modules/Core/app/Rules/LowercaseUniquePair.php::validate::$this->table#1' => [
                'table' => '$this->table',
                'operation' => 'select',
                'justification' => 'tenant predicate via tenant column clause (§4.3 layer 0)',
            ],
            'Modules/Core/app/Rules/ScopedExists.php::validate::$this->table#1' => [
                'table' => '$this->table',
                'operation' => 'select',
                'justification' => 'tenant predicate via tenant column clause (§4.3 layer 0)',
            ],
            'Modules/Core/app/Rules/ScopedUnique.php::validate::$this->table#1' => [
                'table' => '$this->table',
                'operation' => 'select',
                'justification' => 'tenant predicate via tenant column clause (§4.3 layer 0)',
            ],
            'Modules/Core/app/Rules/UniquePair.php::validate::$this->table#1' => [
                'table' => '$this->table',
                'operation' => 'select',
                'justification' => 'central operation: scoped validation rule supplies the tenant predicate (§4.3 layer 0)',
            ],
            'Modules/Core/app/Support/Tenancy/TenancyAudit.php::run::$pivot#1' => [
                'table' => '$pivot',
                'operation' => 'select',
                'justification' => 'central operation: audit traversal with explicit owner/reference comparison (§4.3 layer 0)',
            ],
            'Modules/Core/app/Support/Tenancy/TenancyAudit.php::run::$pivot#2' => [
                'table' => '$pivot',
                'operation' => 'select',
                'justification' => 'central operation: audit traversal with explicit owner/reference comparison (§4.3 layer 0)',
            ],
            'Modules/Core/app/Support/Tenancy/TenancyAudit.php::run::$table#1' => [
                'table' => '$table',
                'operation' => 'select',
                'justification' => 'central operation: audit traversal with explicit owner/reference comparison (§4.3 layer 0)',
            ],
            'Modules/Core/app/Support/Tenancy/TenancyAudit.php::run::$table#2' => [
                'table' => '$table',
                'operation' => 'select',
                'justification' => 'central operation: audit traversal with explicit owner/reference comparison (§4.3 layer 0)',
            ],
            'Modules/Core/app/Support/Tenancy/TenancyAudit.php::run::$table#3' => [
                'table' => '$table',
                'operation' => 'select',
                'justification' => 'central operation: audit traversal with explicit owner/reference comparison (§4.3 layer 0)',
            ],
            'Modules/Discount/app/Actions/Admin/Promotion/ListPromotionAction.php::handle::discount_promotion_categories#1' => [
                'table' => 'discount_promotion_categories',
                'operation' => 'select',
                'justification' => 'central operation: parent-scoped pivot read through the promotion id (§4.3 layer 0)',
            ],
            'Modules/Discount/app/Actions/Admin/Promotion/ListPromotionAction.php::handle::discount_promotion_products#1' => [
                'table' => 'discount_promotion_products',
                'operation' => 'select',
                'justification' => 'central operation: parent-scoped pivot read through the promotion id (§4.3 layer 0)',
            ],
            'Modules/Discount/app/Gateways/PromotionGateway.php::indexByScope::discount_promotion_categories#1' => [
                'table' => 'discount_promotion_categories',
                'operation' => 'select',
                'justification' => 'central operation: parent-scoped pivot read through the promotion id (§4.3 layer 0)',
            ],
            'Modules/Discount/app/Gateways/PromotionGateway.php::indexByScope::discount_promotion_products#1' => [
                'table' => 'discount_promotion_products',
                'operation' => 'select',
                'justification' => 'central operation: parent-scoped pivot read through the promotion id (§4.3 layer 0)',
            ],
            'Modules/Discount/app/Models/Promotion.php::selectedCategoryIds::discount_promotion_categories#1' => [
                'table' => 'discount_promotion_categories',
                'operation' => 'select',
                'justification' => 'central operation: parent-scoped pivot read through the promotion id (§4.3 layer 0)',
            ],
            'Modules/Discount/app/Models/Promotion.php::selectedProductIds::discount_promotion_products#1' => [
                'table' => 'discount_promotion_products',
                'operation' => 'select',
                'justification' => 'central operation: parent-scoped pivot read through the promotion id (§4.3 layer 0)',
            ],
            'Modules/Discount/app/Models/Promotion.php::syncScope::discount_promotion_categories#1' => [
                'table' => 'discount_promotion_categories',
                'operation' => 'delete',
                'justification' => 'central operation: parent-scoped pivot write with ownership asserted by the calling action (§4.3 layer 0)',
            ],
            'Modules/Discount/app/Models/Promotion.php::syncScope::discount_promotion_categories#2' => [
                'table' => 'discount_promotion_categories',
                'operation' => 'insert',
                'justification' => 'central operation: parent-scoped pivot write with ownership asserted by the calling action (§4.3 layer 0)',
            ],
            'Modules/Discount/app/Models/Promotion.php::syncScope::discount_promotion_products#1' => [
                'table' => 'discount_promotion_products',
                'operation' => 'delete',
                'justification' => 'central operation: parent-scoped pivot write with ownership asserted by the calling action (§4.3 layer 0)',
            ],
            'Modules/Discount/app/Models/Promotion.php::syncScope::discount_promotion_products#2' => [
                'table' => 'discount_promotion_products',
                'operation' => 'insert',
                'justification' => 'central operation: parent-scoped pivot write with ownership asserted by the calling action (§4.3 layer 0)',
            ],
            'Modules/Discount/app/Transfer/DiscountTransfer.php::importRedemptions::discount_coupons#1' => [
                'table' => 'discount_coupons',
                'operation' => 'update',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Discount/app/Transfer/DiscountTransfer.php::importRedemptions::discount_redemptions#1' => [
                'table' => 'discount_redemptions',
                'operation' => 'insertGetId',
                'justification' => 'tenant-stamped write (§4.3 layer 0)',
            ],
            'Modules/Discount/app/Transfer/DiscountTransfer.php::importRedemptions::order_orders#1' => [
                'table' => 'order_orders',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Media/app/Actions/Admin/Media/CreateMediaAction.php::handle::media_media_outbox#1' => [
                'table' => 'media_media_outbox',
                'operation' => 'insert',
                'justification' => 'tenant-stamped write (§4.3 layer 0)',
            ],
            'Modules/Media/app/Console/Commands/SweepMediaStagingCommand.php::handle::media_media_outbox#1' => [
                'table' => 'media_media_outbox',
                'operation' => 'select',
                'justification' => 'central operation: declared console maintenance mode (§6.7.2)',
            ],
            'Modules/Media/app/Support/MediaPublicationProcessor.php::claim::media_media_outbox#1' => [
                'table' => 'media_media_outbox',
                'operation' => 'update',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Media/app/Support/MediaPublicationProcessor.php::claimableEntryIds::media_media_outbox#1' => [
                'table' => 'media_media_outbox',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Media/app/Support/MediaPublicationProcessor.php::markFailed::media_media_outbox#1' => [
                'table' => 'media_media_outbox',
                'operation' => 'update',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Media/app/Support/MediaPublicationProcessor.php::process::media_media_outbox#1' => [
                'table' => 'media_media_outbox',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Media/app/Support/MediaPublicationProcessor.php::publish::media_media#1' => [
                'table' => 'media_media',
                'operation' => 'update',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Media/app/Support/MediaPublicationProcessor.php::publish::media_media_outbox#1' => [
                'table' => 'media_media_outbox',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Media/app/Support/MediaPublicationProcessor.php::publish::media_media_outbox#2' => [
                'table' => 'media_media_outbox',
                'operation' => 'update',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Media/app/Support/MediaPublicationProcessor.php::recordFailure::media_media_outbox#1' => [
                'table' => 'media_media_outbox',
                'operation' => 'update',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Order/app/Transfer/OrderTransfer.php::importChildren::$table#1' => [
                'table' => '$table',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Order/app/Transfer/OrderTransfer.php::importChildren::$table#2' => [
                'table' => '$table',
                'operation' => 'delete',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Order/app/Transfer/OrderTransfer.php::importChildren::$table#3' => [
                'table' => '$table',
                'operation' => 'insertGetId',
                'justification' => 'tenant-stamped write (§4.3 layer 0)',
            ],
            'Modules/Order/app/Transfer/OrderTransfer.php::importOrders::order_orders#1' => [
                'table' => 'order_orders',
                'operation' => 'update',
                'justification' => 'tenant-stamped write (§4.3 layer 0)',
            ],
            'Modules/Order/app/Transfer/OrderTransfer.php::importOrders::order_orders#2' => [
                'table' => 'order_orders',
                'operation' => 'insertGetId',
                'justification' => 'tenant-stamped write (§4.3 layer 0)',
            ],
            'Modules/Order/app/Transfer/OrderTransfer.php::orderExists::order_orders#1' => [
                'table' => 'order_orders',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Order/app/Transfer/OrderTransfer.php::refExists::catalog_variants#1' => [
                'table' => 'catalog_variants',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Order/app/Transfer/OrderTransfer.php::refExists::discount_coupons#1' => [
                'table' => 'discount_coupons',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Order/app/Transfer/OrderTransfer.php::refExists::discount_promotions#1' => [
                'table' => 'discount_promotions',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Order/app/Transfer/OrderTransfer.php::refExists::shipping_methods#1' => [
                'table' => 'shipping_methods',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Order/app/Transfer/OrderTransfer.php::refExists::tax_classes#1' => [
                'table' => 'tax_classes',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Order/app/Transfer/OrderTransfer.php::variantExists::catalog_variants#1' => [
                'table' => 'catalog_variants',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Payment/app/Transfer/PaymentTransfer.php::importMethods::payment_payment_methods#1' => [
                'table' => 'payment_payment_methods',
                'operation' => 'insertGetId',
                'justification' => 'tenant-stamped write (§4.3 layer 0)',
            ],
            'Modules/Payment/app/Transfer/PaymentTransfer.php::importPayments::payment_payments#1' => [
                'table' => 'payment_payments',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Payment/app/Transfer/PaymentTransfer.php::importPayments::payment_payments#2' => [
                'table' => 'payment_payments',
                'operation' => 'insertGetId',
                'justification' => 'tenant-stamped write (§4.3 layer 0)',
            ],
            'Modules/Payment/app/Transfer/PaymentTransfer.php::importRefunds::payment_refunds#1' => [
                'table' => 'payment_refunds',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Payment/app/Transfer/PaymentTransfer.php::importRefunds::payment_refunds#2' => [
                'table' => 'payment_refunds',
                'operation' => 'insertGetId',
                'justification' => 'tenant-stamped write (§4.3 layer 0)',
            ],
            'Modules/Payment/app/Transfer/PaymentTransfer.php::orderExists::order_orders#1' => [
                'table' => 'order_orders',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Payment/app/Transfer/PaymentTransfer.php::paymentExists::payment_payments#1' => [
                'table' => 'payment_payments',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Support/app/Transfer/SupportTransfer.php::importMessages::support_ticket_messages#1' => [
                'table' => 'support_ticket_messages',
                'operation' => 'insertGetId',
                'justification' => 'tenant-stamped write (§4.3 layer 0)',
            ],
            'Modules/Support/app/Transfer/SupportTransfer.php::importTickets::support_tickets#1' => [
                'table' => 'support_tickets',
                'operation' => 'insertGetId',
                'justification' => 'tenant-stamped write (§4.3 layer 0)',
            ],
            'Modules/Support/app/Transfer/SupportTransfer.php::messageExists::support_ticket_messages#1' => [
                'table' => 'support_ticket_messages',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
            'Modules/Support/app/Transfer/SupportTransfer.php::ticketExists::support_tickets#1' => [
                'table' => 'support_tickets',
                'operation' => 'select',
                'justification' => 'tenant predicate via TenantQueryScope (§4.3 layer 0)',
            ],
        ],
        // Bare unique/exists rules are permitted only on installation-level
        // tables, and only at the exact sites listed here.
        'validation_rules' => [
            'Modules/Auth/app/Http/Requests/Admin/Role/RoleRequest.php::rules::exists:auth_permissions#1' => [
                'table' => 'auth_permissions',
                'kind' => 'exists',
            ],
            // RoleRequest's former unique:auth_roles site became the
            // ScopedUnique rule (§5 — the spatie team key); the scoped rule
            // carries the tenant predicate itself and leaves no raw
            // Rule::unique site to register.
            'Modules/Auth/app/Http/Requests/Admin/Token/MachineTokenRequest.php::rules::exists:auth_users#1' => [
                'table' => 'auth_users',
                'kind' => 'exists',
            ],
            'Modules/Auth/app/Http/Requests/Admin/User/UserRequest.php::rules::exists:auth_roles#1' => [
                'table' => 'auth_roles',
                'kind' => 'exists',
            ],
            'Modules/Auth/app/Http/Requests/Admin/User/UserRequest.php::rules::unique:auth_users#1' => [
                'table' => 'auth_users',
                'kind' => 'unique',
            ],
            'Modules/Core/app/Http/Requests/Admin/Currency/StoreCurrencyRequest.php::rules::unique:core_currencies#1' => [
                'table' => 'core_currencies',
                'kind' => 'unique',
            ],
            'Modules/Core/app/Http/Requests/Admin/Currency/UpdateCurrencyRequest.php::rules::unique:core_currencies#1' => [
                'table' => 'core_currencies',
                'kind' => 'unique',
            ],
            'Modules/Core/app/Transfer/CoreTransfer.php::countryRules::unique:core_countries#1' => [
                'table' => 'core_countries',
                'kind' => 'unique',
            ],
            'Modules/Tenant/app/Http/Requests/Admin/Tenant/TenantRequest.php::rules::unique:tenant_tenants#1' => [
                'table' => 'tenant_tenants',
                'kind' => 'unique',
            ],
            'Modules/Tenant/app/Http/Requests/User/Onboarding/ProvisionTenantRequest.php::rules::unique:tenant_tenants#1' => [
                'table' => 'tenant_tenants',
                'kind' => 'unique',
            ],
        ],

        // Console commands that must enter MODE_CENTRAL through the registry.
        'command_modes' => [
            // Declared here: the commands whose own source enters central
            // mode (the architecture test checks exactly that symmetry).
            // The FULL §6.7.2 declaration table (dispatch-deliveries,
            // prune, the order/media sweeps) is Phase 5 work (§8 Phase 5;
            // Phase 2 deviation 8) — retry-delivery's central bootstrap
            // lookup (§6.2) lands with Phase 4, so it declares itself now.
            'tenancy:audit' => 'central',
            'webhooks:retry-delivery' => 'central',
        ],
    ],

    // Data-transfer standard registration — picked up by the Transfer
    // module's registry (see docs/transfer.md).
    'transfer' => [
        'class' => CoreTransfer::class,

        // Import-lease protocol (postgres-migration §3.1): how long an
        // import waits for a contended destination-module lease before
        // failing with `import_in_progress`, and how long an acquired
        // lease lives between write-unit renewals (DB clock).
        'lock_timeout' => (int) env('TRANSFER_LOCK_TIMEOUT', 10),
        'lock_lease_ttl' => (int) env('TRANSFER_LOCK_LEASE_TTL', 60),
    ],
];
