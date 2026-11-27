<?php

namespace Modules\Core\Support\Tenancy;

/**
 * The Phase 2 ownership inventory (multi-tenancy §4.4).
 *
 * Key-only tables (machine tokens, spatie team tables, tenant metadata) and
 * the nine tenant-less pivots are deliberately absent: they either belong to
 * the installation or inherit ownership from both parents and are audited
 * through the pivot relation map instead.
 */
final class TenantTableInventory
{
    /** @var list<string> */
    private const array STORE_OWNED = [
        'audit_logs',
        'cart_cart_items',
        'cart_carts',
        'catalog_attribute_group_translations',
        'catalog_attribute_groups',
        'catalog_attribute_templates',
        'catalog_attribute_translations',
        'catalog_attribute_value_translations',
        'catalog_attribute_values',
        'catalog_attributes',
        'catalog_brand_translations',
        'catalog_brands',
        'catalog_categories',
        'catalog_category_translations',
        'catalog_featured_items',
        'catalog_images',
        'catalog_imports',
        'catalog_option_translations',
        'catalog_option_value_translations',
        'catalog_option_values',
        'catalog_options',
        'catalog_product_attribute_values',
        'catalog_product_comment_reactions',
        'catalog_product_comments',
        'catalog_product_seo_translations',
        'catalog_product_shipping',
        'catalog_product_translations',
        'catalog_products',
        'catalog_variants',
        'contact_messages',
        'contact_newsletter_subscribers',
        'content_featured_items',
        'content_page_translations',
        'content_pages',
        'content_post_categories',
        'content_post_category_translations',
        'content_post_comments',
        'content_post_seo_translations',
        'content_post_translations',
        'content_posts',
        'core_idempotency_keys',
        'discount_coupons',
        'discount_promotions',
        'discount_redemptions',
        'inventory_inventory_stocks',
        'media_folders',
        'media_media',
        'media_media_outbox',
        'menu_menu_link_translations',
        'menu_menu_links',
        'menu_menu_translations',
        'menu_menus',
        'notification_notifications',
        'order_credit_notes',
        'order_document_sequences',
        'order_invoice_items',
        'order_invoices',
        'order_order_addresses',
        'order_order_items',
        'order_orders',
        'order_returns',
        'payment_driver_credentials',
        'payment_payment_methods',
        'payment_payments',
        'payment_refunds',
        'setting_settings',
        'setting_translations',
        'shipping_method_translations',
        'shipping_methods',
        'shipping_rates',
        'shipping_shipments',
        'shipping_zones',
        'support_ticket_messages',
        'support_tickets',
        'tax_classes',
        'tax_rates',
        'transfer_imports',
        'transfer_remaps',
        'user_wishlist_items',
        'webhook_deliveries',
        'webhook_endpoints',
        'webhook_events',
    ];

    /**
     * Tables whose NULL tenant_id is a platform row, not a violation.
     *
     * @var list<string>
     */
    private const array PLATFORM_TIER = ['audit_logs', 'setting_settings'];

    /**
     * Tenant-less pivots between two store-owned tables (§4.4).
     *
     * @var list<string>
     */
    private const array PIVOTS = [
        'catalog_attribute_template_groups',
        'catalog_category_attribute',
        'catalog_category_product',
        'catalog_variant_option_values',
        'content_post_category_post',
        'content_post_product',
        'discount_promotion_categories',
        'discount_promotion_products',
        'shipping_zone_countries',
    ];

    /** @return list<string> */
    public static function storeOwned(): array
    {
        return self::STORE_OWNED;
    }

    /** @return list<string> */
    public static function platformTier(): array
    {
        return self::PLATFORM_TIER;
    }

    /** @return list<string> */
    public static function pivots(): array
    {
        return self::PIVOTS;
    }

    public static function isStoreOwned(string $table): bool
    {
        return in_array($table, self::STORE_OWNED, true);
    }
}
