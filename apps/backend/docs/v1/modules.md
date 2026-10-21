
# 👋 Developer Onboarding

Welcome! This guide helps you understand all modules in under 30 minutes.


##   Big Picture 

### Modules
```
├── Audit
├── Auth
├── Cart
├── Catalog
├── Contact
├── Content
├── Core
├── Discount
├── Inventory
├── Media
├── Menu
├── Notification
├── Order
├── Payment
├── Setting
├── Shipping
├── Support
├── Tax
└── User
```

### Events
```
├── Auth
│   ├── UserDeactivatedEvent.php
│   ├── UserEmailVerifiedEvent.php
│   ├── UserSignedInEvent.php
│   └── UserSignedUpEvent.php
├── Cart
│   ├── CartCheckedOutEvent.php
│   ├── CartCreatedEvent.php
│   ├── CartItemAddedEvent.php
│   └── CartItemRemovedEvent.php
├── Catalog
│   ├── ProductActivatedEvent.php
│   ├── ProductCreatedEvent.php
│   ├── ProductDeactivatedEvent.php
│   ├── ProductDeletedEvent.php
│   └── ProductUpdatedEvent.php
├── Content
│   ├── PagePublishedEvent.php
│   └── PageUnpublishedEvent.php
├── Inventory
│   ├── StockDecreasedEvent.php
│   ├── StockDepletedEvent.php
│   └── StockIncreasedEvent.php
├── Media
│   ├── MediaCreatedEvent.php
│   └── MediaDeletedEvent.php
├── Order
│   ├── OrderCancelledEvent.php
│   ├── OrderCompletedEvent.php
│   ├── OrderCreatedEvent.php
│   ├── OrderPaidEvent.php
│   ├── OrderPendingEvent.php
│   └── OrderShippedEvent.php
├── Payment
│   ├── PaymentFailedEvent.php
│   ├── PaymentInitiatedEvent.php
│   └── PaymentSucceededEvent.php
├── Setting
│   └── SettingUpdatedEvent.php
├── Shipping
│   ├── ShipmentCreatedEvent.php
│   ├── ShipmentDeliveredEvent.php
│   └── ShipmentShippedEvent.php
└── User
    └── UserProfileUpdatedEvent.php
```


---
## Audit
This module records admin activity to an audit log and exposes it in the admin panel

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| audit_logs                   | One row per recorded admin action (acting user, module, action, changes) |

### 🔔 Events Emitted
| Event | When |
|----|----|
| None  | None |

### 👂 Events Listened To
| Event | Reaction       |
|-------|----------------|
| All domain events | Records admin actions to `audit_logs` (listener lives here) |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| None  | None |



---
## Auth
This module is responsible for managing authentication and authorization.

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| users_users                  |  |
| users_otp_codes              |  |

### 🔔 Events Emitted
| Event | When |
|----|----|
| UserDeactivatedEvent | Trigger |
| UserEmailVerifiedEvent | Trigger |
| UserSignedInEvent | Trigger |
| UserSignedUpEvent | Trigger |

### 👂 Events Listened To
| Event | Reaction |
|-------|----|
| None  | None |

### 🔗 Dependencies
| Module | Reason |
|--------|--------|
| None   | None   |


---
## Cart
This module is responsible for Cart

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| cart_cart                    |  |
| cart_cart_items              |  |

### 🔔 Events Emitted
| Event | When |
|----|----|
| CartCheckedOutEvent | Trigger |
| CartCreatedEvent | Trigger |
| CartItemAddedEvent | Trigger |
| CartItemRemovedEvent | Trigger |

### 👂 Events Listened To
| Event | Reaction |
|-------|----|
| None  | None |

### 🔗 Dependencies
| Module    | Reason                          |
|-----------|---------------------------------|
| Inventory | To check user's address         |
| Order     | To create new order in checkout |
| User      | To check products availability  |




---
## Catalog
This module is responsible for products and all related entities like Variants, Options, Images, Categories

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| catalog_products             |  |
| catalog_variants             |  |
| catalog_options              |  |
| catalog_option_values        |  |
| catalog_categories           |  |
| catalog_images               |  |

### 🔔 Events Emitted
| Event | When |
|----|----|
| ProductCreatedEvent | Trigger |
| ProductUpdatedEvent | Trigger |
| ProductDeletedEvent | Trigger |
| ProductActivatedEvent | Trigger |
| ProductDeactivatedEvent | Trigger |

### 👂 Events Listened To
| Event | Reaction |
|-------|----|
| None  | None |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| Media  | To upload images |





---
## Content
This module is responsible for providing content like Pages

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| content_pages                |  |

### 🔔 Events Emitted
| Event | When |
|----|----|
| PagePublishedEvent | Trigger |
| PageUnpublishedEvent | Trigger |

### 👂 Events Listened To
| Event | Reaction |
|-------|----|
| None  | None |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| Notification  | Notifies admins by email when a contact message is submitted (listener lives in the Notification module) |



---
## Contact
This module stores messages submitted through the storefront contact-us form and manages them in the admin inbox

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| contact_messages                   | Contact form submissions (name, email, phone, subject, content, status, ip_address) |

### 🔔 Events Emitted
| Event | When |
|----|----|
| ContactMessageCreatedEvent  | Public contact form submission stored (public endpoint, per-IP rate limited) |
| ContactMessageStatusUpdatedEvent  | Admin changed a message status (unread/read/replied/archived) |
| ContactMessageDeletedEvent  | Admin deleted a message |

### 👂 Events Listened To
| Event | Reaction       |
|-------|----------------|
| None   | None |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| None  | None |



---
## Core
This module is shared with all modules

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| core_idempotency_keys                        |  |

### 🔔 Events Emitted
| Event | When |
|----|----|
| None  | None |

### 👂 Events Listened To
| Event | Reaction       |
|-------|----------------|
| All   | To save events |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| None  | None |







---
## Discount
This module is responsible for coupons (with redemption guards) and product/category-scoped promotions

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| discount_coupons             | Coupon definitions |
| discount_redemptions         | One row per coupon redemption (guard against over-redemption) |
| discount_promotions          | Promotion definitions |
| discount_promotion_products  | Product scope of a promotion |
| discount_promotion_categories| Category scope of a promotion |

### 🔔 Events Emitted
| Event | When |
|----|----|
| None  | None |

### 👂 Events Listened To
| Event | Reaction       |
|-------|----------------|
| None   | None |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| Cart   | Coupon application + discount totals at checkout (via Discount gateway) |
| Catalog | Promotion price resolution for listing/PDP prices (via Discount gateway) |



---
## Inventory
This module is responsible for checking products stocks

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| inventory_inventory_stocks                   |  |

### 🔔 Events Emitted
| Event | When |
|----|----|
| StockDecreasedEvent  | None |
| StockDepletedEvent  | None |
| StockIncreasedEvent  | None |

### 👂 Events Listened To
| Event | Reaction       |
|-------|----------------|
| None   | None |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| None  | None |




---
## Media
This module is responsible for uploading new media

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| media_media                  |  |

### 🔔 Events Emitted
| Event | When |
|----|----|
| MediaCreatedEvent  | None |
| MediaDeletedEvent  | None |

### 👂 Events Listened To
| Event | Reaction       |
|-------|----------------|
| None   | None |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| None  | None |





---
## Menu
This module is responsible for managing navigation menus and their nested links

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| menu_menus                        | Menus per location (header/footer) with is_active toggle |
| menu_menu_translations            | Per-language menu title/description |
| menu_menu_links                   | Nested links of a menu (parent_id adjacency list, position, url, target) |
| menu_menu_link_translations       | Per-language link title/description |

### 🔔 Events Emitted
| Event | When |
|----|----|
| MenuCreatedEvent  | Menu created |
| MenuUpdatedEvent  | Menu updated |
| MenuDeletedEvent  | Menu deleted |
| MenuLinkCreatedEvent  | Menu link created |
| MenuLinkUpdatedEvent  | Menu link updated (incl. reparenting with circular-move guard) |
| MenuLinkDeletedEvent  | Menu link deleted (with its subtree) |

### 👂 Events Listened To
| Event | Reaction       |
|-------|----------------|
| None   | None |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| None  | None |





---

## Notification
This module delivers notifications across four channels (email, database, web-push, Telegram) behind builder/template/channel contracts, with a per-recipient preference matrix

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| notification_notifications   | Database-channel notification rows |
| notification_preferences     | Per-recipient channel/topic preferences |
| notification_push_subscriptions  | Web-push (VAPID) subscriptions |
| notification_telegram_subscriptions | Telegram chat subscriptions |

### 🔔 Events Emitted
| Event | When |
|----|----|
| None  | None |

### 👂 Events Listened To
| Event | Reaction       |
|-------|----------------|
| Events from all modules | ~40 listeners turn domain events (orders, payments, tickets, contact, …) into notifications |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| None  | Consumes events only; channels are self-contained |



---

## Order
This module is responsible for Ordering

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| order_orders                       |  |
| order_order_items                      |  |
| order_order_addresses                     |  |

### 🔔 Events Emitted
| Event | When |
|----|----|
| OrderCancelledEvent  | None |
| OrderCompletedEvent  | None |
| OrderCreatedEvent  | None |
| OrderPaidEvent  | None |
| OrderPendingEvent  | None |
| OrderShippedEvent  | None |

### 👂 Events Listened To
| Event | Reaction       |
|-------|----------------|
| None   | None |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| None  | None |









---
## Payment
This module is responsible for payments

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| payment_payments                     |  |

### 🔔 Events Emitted
| Event | When |
|----|----|
| PaymentFailedEvent  | None |
| PaymentInitiatedEvent  | None |
| PaymentSucceededEvent  | None |

### 👂 Events Listened To
| Event | Reaction       |
|-------|----------------|
| None   | None |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| None  | None |









---
## Setting
This module is responsible for keeping settings

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| setting_settings             |  |

### 🔔 Events Emitted
| Event | When |
|----|----|
| SettingUpdatedEvent  | None |

### 👂 Events Listened To
| Event | Reaction       |
|-------|----------------|
| None   | None |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| None  | None |








---

## Shipping
This module is responsible for shipping methods, zones, rates and per-order shipments with tracking

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| shipping_methods             |  |
| shipping_method_translations |  |
| shipping_zones               |  |
| shipping_zone_countries      |  |
| shipping_rates               |  |
| shipping_shipments           |  |

### 🔔 Events Emitted
| Event | When |
|----|----|
| ShipmentCreatedEvent  | Shipment created for an order |
| ShipmentShippedEvent  | Shipment transitioned to shipped |
| ShipmentDeliveredEvent  | Shipment transitioned to delivered |

### 👂 Events Listened To
| Event | Reaction       |
|-------|----------------|
| None   | None |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| Core  | Contracts, country rule and default language |
| Order  | findOrder / markAsShipped / markAsDelivered |
| User  | getAddress for checkout quotes |





---
## Support
This module is responsible for customer support tickets with threaded messages

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| support_tickets              | Tickets (status: open/closed, order reference) |
| support_ticket_messages      | Threaded messages on a ticket (customer/admin sender) |

### 🔔 Events Emitted
| Event | When |
|----|----|
| TicketCreatedEvent  | Customer opens a ticket |
| TicketMessageCreatedEvent  | Reply added to a ticket |
| TicketStatusUpdatedEvent  | Status changed (open/closed/reopened) |
| TicketDeletedEvent  | Admin deletes a ticket |

### 👂 Events Listened To
| Event | Reaction       |
|-------|----------------|
| None   | None |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| Order  | Verifies order ownership when a ticket references an order code |
| Notification | Notifies participants on ticket events (listener lives in Notification) |



---

## Tax
This module is responsible for tax classes and rates, with exact integer math and tax snapshots on orders

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| tax_classes                  | Tax classes (e.g. standard, reduced) |
| tax_rates                    | Rates per class × country |

### 🔔 Events Emitted
| Event | When |
|----|----|
| None  | None |

### 👂 Events Listened To
| Event | Reaction       |
|-------|----------------|
| None   | None |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| None  | None (consumed by Cart/Order via the Tax gateway) |



---

## User
This module is responsible for users

### 🗂 Tables
| Table (DB Prefix_Table Name) | Description |
|------------------------------|----|
| user_users                        |  |

### 🔔 Events Emitted
| Event | When |
|----|----|
| UserProfileUpdatedEvent  | None |

### 👂 Events Listened To
| Event | Reaction       |
|-------|----------------|
| None   | None |

### 🔗 Dependencies
| Module | Reason           |
|--------|------------------|
| None  | None |





