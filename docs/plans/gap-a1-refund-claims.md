# Plan gap-A1 — Per-gateway refund claim honesty (capability surfaced, claims footnoted)

> **Purpose of this document:** Execution plan for Phase A task **A.1** of
> `docs/PLATFORM_GAPS_ROADMAP.md`. Two outcomes: (1) the platform never *implies* all 8
> gateways are refundable — every public claim carries the coverage footnote; (2) the
> admin sees refund capability *before* clicking, instead of discovering it as an API
> error. All facts below verified against the code on 2026-11-03.
>
> **Created:** 2026-11-03 · **Owner:** solo maintainer · **Status:** **DONE 2026-11-03**
>
> **Size:** half a day (backend ~1h, admin FE ~2h, i18n + tests + docs the rest).
> **Not started until explicitly picked up.** Conventional commits; never commit unless
> asked. Heavy commands prefixed `nice -n 10`; scope test runs to `Modules/Payment`.

## Outcomes (2026-11-03)

- Implemented exactly per §3: `RefundableDriver` marker (Stripe/PayPal/Manual),
  `PaymentDriverManager::supportsRefund()`, `is_refundable` on both admin resources,
  methods-page badges + hint, gated "Issue refund" with tooltip, 6-locale i18n,
  four new Pest tests, docs (THIRD_PARTY_SERVICES pointer, Part F footnote wording,
  CHANGELOG). Bruno request untouched (optional per §3.5 — no schema assertions there).
- **Verified:** Payment suite green (254 tests); full backend suite **920 passed /
  3,025 assertions**; admin build green; i18n parity green; phpstan neutral after
  extending the baseline by exactly the four touched test files' new `TestCall`
  false-positive occurrences (24 occurrences; 29→34 entries).
- **Discovered en route:** main's phpstan CI is **already red at 188 errors** — the
  Oct-31 language commits postdate the last baseline regen (2026-10-29). Those 188
  were deliberately NOT absorbed here; regenerate or fix separately.
- **Remaining manual check:** dev-compose click-through (methods-page badges; disabled
  refund tooltip on an Adyen payment; Stripe refund still works).

---

## 1. Verified current state (2026-11-03)

**Driver reality** (`apps/backend/Modules/Payment/app/Gateways/Drivers/`):

| Driver | `refund()` | Mechanism |
|---|---|---|
| Stripe | ✅ implemented | `StripePaymentDriver.php:98` |
| PayPal | ✅ implemented | `PaypalPaymentDriver.php:97` |
| Manual | ✅ implemented (bookkeeping only) | `ManualPaymentDriver.php:28` |
| Adyen, MercadoPago, Checkout.com, Razorpay, NowPayments | ❌ throws | `use CannotRefund;` → `PaymentException::refundNotSupported()` → lang key `payment.refund.not_supported` ("This payment method does not support refunds yet") |

**Contract:** `Modules/Core/app/Contracts/Gateways/Payment/PaymentDriverInterface.php`
declares `initiate` / `handleWebhook` / `refund` — **no capability query exists**.
`Modules/Payment/app/Gateways/PaymentDriverManager.php` exposes `forMethod()`,
`forName()`, `isRegistered()`, `isConfigured()` (enum-typed).

**Admin surface today:**
- `GET /api/v1/payment/admin/methods` → `ListPaymentMethodAction` (lazy-creates a row per
  registered enum case) → `PaymentMethodResource` exposes `id, method, is_active,
  sort_order, is_configured, is_registered` — **nothing refund-related**.
- `GET /api/v1/payment/admin/payments` → admin `PaymentResource` exposes `method`, `status`,
  … — no refundability. The payments page gates "Issue refund" on
  `status === 'success'` only (`apps/admin/src/features/payment/payments/components/data-table-row-actions.tsx:26`)
  → an Adyen payment shows a clickable refund button that explodes into an error toast.
- Methods page renders badges + one hint line per row
  (`features/payment/methods/components/method-row.tsx`), i18n in
  `apps/admin/public/locales/{en,fa,de,fr,es,ar}/payment/payment-method.json`
  (`form.statuses.state.*`, `form.help_texts.*`; all 8 method labels already exist).

**Docs reality:** `docs/THIRD_PARTY_SERVICES.md:25–36` **already documents refunds
honestly** (per-gateway table: Yes / Yes / bookkeeping / "Not yet") — this file stays
authoritative. Root README makes no gateway-count claim today (line 18 "Stripe payments"
is simply true). The "8 payment gateways with webhook verification" row is *planned* for
the comparison table in launch-readiness **Part F** (not yet executed) — the footnote
must be baked into that table's spec so it never ships bare.

## 2. Design decision — how capability is signalled

**Chosen: marker interface `RefundableDriver`** in Core contracts, checked via the manager.

```php
// Modules/Core/app/Contracts/Gateways/Payment/RefundableDriver.php
interface RefundableDriver extends PaymentDriverInterface {}
```

- Stripe, PayPal, Manual drivers add `implements RefundableDriver` (one line each).
- `PaymentDriverManager::supportsRefund(PaymentMethodEnum $method): bool` —
  `isRegistered() && forMethod() instanceof RefundableDriver`.
- Capability lives **beside behavior and can't drift**: B.4 (gap roadmap) adds refunds to
  Adyen et al. by implementing the marker exactly where `use CannotRefund;` gets removed.
- Additive forever: third-party extension drivers (marketplace Phase 2+) that don't know
  about the marker keep compiling. An extra method on `PaymentDriverInterface` was
  rejected — PHP interfaces have no default bodies, so every future extension driver
  would be forced to implement it; this also respects the additive-only contract policy
  (marketplace roadmap §5.1).

## 3. Work items

### 3.1 Backend — capability contract + API exposure (~1h)

- [ ] Add `RefundableDriver` marker interface (Core contracts, path above).
- [ ] `StripePaymentDriver`, `PaypalPaymentDriver`, `ManualPaymentDriver`: implement it.
- [ ] `PaymentDriverManager::supportsRefund(PaymentMethodEnum $method): bool`.
- [ ] `PaymentMethodSchema`: add `IS_REFUNDABLE = 'is_refundable'` constant.
- [ ] Admin `PaymentMethodResource`: add `is_refundable` (via manager; false when
      unregistered).
- [ ] Admin `PaymentResource`: add `is_refundable` for the payment's method (independent
      of status — the FE combines).
- [ ] **Deliberately excluded:** user/shopper `PaymentMethodResource` — checkout offers
      no refund choice; nothing for a customer to see.
- Scramble picks the new fields up automatically (resources are typed) — verify `/docs/api`
      still generates.

### 3.2 Admin — methods page badge + hint (~1h)

- [ ] `features/payment/methods/data/schema.ts`: add `is_refundable: boolean`.
- [ ] `method-row.tsx`: second muted line under the existing hint — badge
      `outline` "Refunds: built-in" or "Refunds: gateway dashboard" + short help text.
      Do **not** overload the primary hint; it currently encodes
      registered/configured state.
- [ ] i18n keys in `payment-method.json`, **all 6 locales** (en/fa/de/fr/es/ar) —
      suggested names (final wording owner-reviewed, agent-drafted per ROADMAP convention):
      - `form.statuses.state.refunds-supported` → "Refunds: built-in"
      - `form.statuses.state.refunds-not-supported` → "Refunds: gateway dashboard"
      - `form.help_texts.refund-supported` → "Full/partial refunds can be issued from this admin."
      - `form.help_texts.refund-not-supported` → "This driver has no refund API wired yet — refund in the gateway's dashboard, then record it as Manual if needed."

### 3.3 Admin — payments page: gate the refund button (~1h)

- [ ] `features/payment/payments/data/data.ts` (row type): add `is_refundable`.
- [ ] `data-table-row-actions.tsx`: keep "Issue refund" **visible but disabled** with a
      tooltip when `!is_refundable` (visible-disabled beats hidden: honest, discoverable,
      matches A.1's spirit). "Refund history" stays always-available.
      `refundable = status === 'success' && is_refundable` — tooltip names which
      condition failed (i18n keys in `payment/payment.json`, 6 locales).
- [ ] `refund-dialog.tsx`: no change (unreachable when gated correctly).

### 3.4 Tests (Pest, `Modules/Payment/tests/`)

- [ ] `supportsRefund()` matrix: true for stripe/paypal/manual; false for the other five;
      false for unregistered name. Mirror existing manager/driver test file conventions.
- [ ] Admin methods list: `is_refundable` present and correct per row (extend the
      existing methods-list test if present, else add).
- [ ] Admin payments list: `is_refundable` reflects the payment's method.
- [ ] Unchanged behavior guard: refund attempt on a `CannotRefund` driver still throws
      `payment.refund.not_supported` (may already exist — check before writing).

### 3.5 Docs + claim wording (~1h)

- [ ] `docs/THIRD_PARTY_SERVICES.md` refund table stays as-is (verified accurate);
      add one sentence under §Refunds pointing at the admin badge so the two stay linked.
- [ ] **Comparison-table footnote spec** — Part F of `launch-readiness.md` must use this
      exact wording for the gateways row (add it to that plan's §F.2 so it can't drift):
      > "8 payment gateways with webhook verification; built-in refunds: Stripe, PayPal,
      > Manual — Adyen, MercadoPago, Checkout.com, Razorpay refund via the gateway
      > dashboard (driver support roadmaped); NowPayments (crypto) has no provider-side
      > refunds."
- [ ] Root README: no bare gateway claims exist today — nothing to fix; do not add one
      without the footnote (policy below).
- [ ] `CHANGELOG.md` `[Unreleased]` → Added: per-driver refund capability in the admin
      (methods + payments surfaces).
- [ ] Bruno: optional only — the methods list request has no schema assertions; skip
      unless touching that folder anyway.

## 4. Verification (in order)

1. `nice -n 10 vendor/bin/pest Modules/Payment/tests` (backend, scoped).
2. `nice -n 10 pnpm build` in `apps/admin` + `node scripts/check-i18n-parity.mjs` (or via CI job locally).
3. Click-through on dev compose: methods page shows the two badge states; an Adyen
   payment's "Issue refund" is disabled with the tooltip; a Stripe payment refunds as before.
4. Full backend suite once before calling it done (CPU discipline: not after every edit).

## 5. Policy this task writes down (add to gap roadmap §6 when committing)

**"Refundability is part of a gateway claim"** — any gateway advertised anywhere
(README, comparison table, release notes, landing copy) carries either a working refund
driver or the named footnote. The `RefundableDriver` marker is the single source of
truth; `THIRD_PARTY_SERVICES.md` mirrors it, docs never invent their own list.

## 6. Out of scope (do not creep)

- Implementing refunds for Adyen/MercadoPago/Checkout.com/Razorpay — gap roadmap **B.4**.
- Removing `PaymentMethodEnum` / DB-driven methods — marketplace roadmap **Phase 0**
  (the marker works identically for string-keyed drivers; `forName()` already exists).
- Recording a manual/offline refund from the dashboard as bookkeeping — separate small
  feature if wanted later.

## 7. Suggested commits

1. `feat(backend/payment): expose per-driver refund capability`
2. `feat(admin): surface refund capability on methods and payments`
3. `docs(monorepo): footnote per-gateway refund coverage in claim sites`
