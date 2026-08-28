<?php

namespace Modules\Notification\Schemas\Notification;

enum NotificationCodeEnum: string
{
    // Auth
    case AUTH_USER_SIGNED_UP = 'auth.user.signed_up';
    case AUTH_USER_EMAIL_VERIFIED = 'auth.user.email_verified';
    case AUTH_USER_SIGNED_IN = 'auth.user.signed_in';
    case AUTH_USER_DEACTIVATED = 'auth.user.deactivated';

    // CART
    case CART_CHECKOUT_STARTED = 'cart.checkout.started';

    // Catalog
    case CATALOG_PRODUCT_CREATED = 'catalog.product.created';
    case CATALOG_PRODUCT_UPDATED = 'catalog.product.updated';
    case CATALOG_PRODUCT_ACTIVATED = 'catalog.product.activated';
    case CATALOG_PRODUCT_DEACTIVATED = 'catalog.product.deactivated';
    case CATALOG_PRODUCT_DELETED = 'catalog.product.deleted';

    // Content
    case CONTENT_PAGE_PUBLISHED = 'content.page.published';
    case CONTENT_PAGE_UNPUBLISHED = 'content.page.unpublished';

    // Inventory
    case INVENTORY_STOCK_INCREASED = 'inventory.stock.increased';
    case INVENTORY_STOCK_DEPLETED = 'inventory.stock.depleted';

    // ORDER
    case ORDER_ORDER_CREATED = 'order.order.created';
    case ORDER_ORDER_PENDING = 'order.order.pending';
    case ORDER_ORDER_PAID = 'order.order.paid';
    case ORDER_ORDER_COMPLETED = 'order.order.completed';
    case ORDER_ORDER_CANCELLED = 'order.order.cancelled';
    case ORDER_ORDER_SHIPPED = 'order.order.shipped';

    // Payment
    case PAYMENT_PAYMENT_INITIATED = 'payment.payment.initiated';
    case PAYMENT_PAYMENT_SUCCEEDED = 'payment.payment.succeeded';
    case PAYMENT_PAYMENT_FAILED = 'payment.payment.failed';

    // Setting
    case SETTING_SETTING_UPDATED = 'setting.setting.updated';

    // User
    case USER_PROFILE_UPDATED = 'user.profile.updated';
}
