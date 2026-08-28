
# 👋 Developer Onboarding

Welcome! This guide helps you understand all modules in under 30 minutes.


##   Big Picture 

### Modules
```
├── Auth
├── Cart
├── Catalog
├── Content
├── Core
├── Inventory
├── Media
├── Order
├── Payment
├── Setting
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
└── User
    └── UserProfileUpdatedEvent.php
```


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





