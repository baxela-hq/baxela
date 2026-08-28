
# 👋 Developer Onboarding

Welcome! This guide helps you understand the platform in under 30 minutes.

## 1️⃣ Core Concepts
- Modular monolith
- Event-driven
- Domain-first design

## 2️⃣ Folder Structure
```text
Modules/
 ├── Order
 ├── Inventory
 ├── Payment
``` 

Each module is self-contained.

## 3️⃣ Events & Flow
Every important action emits an event.
See `/docs/flows`.

##4️⃣ Adding a New Feature
1. Identify module
2. Add domain logic
3. Emit event
4. Listen from other modules

## 5️⃣ Shared Hosting Mode
- Sync events
- No workers
- File cache


