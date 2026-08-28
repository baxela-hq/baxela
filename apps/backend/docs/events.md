# Events

## 🧩 Core Module
```
Core.EventDispatched
Core.CacheCleared
```

## 👤 Identity Module
```
Identity.UserRegistered
Identity.CustomerCreated
Identity.CustomerUpdated
```

## 📦 Catalog Module
```
Catalog.ProductCreated
Catalog.ProductUpdated
Catalog.ProductDeleted
Catalog.ProductPublished
Catalog.ProductUnpublished
```

## 🧮 Inventory Module
```
Inventory.StockCreated
Inventory.StockIncreased
Inventory.StockDecreased
Inventory.StockReserved
Inventory.StockReleased
Inventory.StockReservationFailed
```

## 🛒 Order Module (MOST IMPORTANT)
```
Order.OrderDraftCreated
Order.OrderValidated
Order.OrderRejected

Order.OrderReadyForPayment
Order.OrderPaymentFailed
Order.OrderPaid

Order.OrderProcessingStarted
Order.OrderCompleted
Order.OrderCancelled
```

## 💳 Payment Module
```
Payment.PaymentIntentCreated
Payment.PaymentSucceeded
Payment.PaymentFailed
Payment.PaymentCancelled
```

## 💰 Pricing Module
```
Pricing.TotalsCalculated
Pricing.TaxCalculated
Pricing.DiscountApplied
```

## ✉️ Notification Module
```
Notification.EmailQueued
Notification.EmailSent
Notification.EmailFailed
```

