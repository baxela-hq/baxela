# Third-Party Services

External services and providers Baxela needs. Baxela is deliberately self-hosted —
database, cache, realtime, and dev mail all run in the Docker stack. The only external
accounts **required** to launch are a payment gateway, a production email provider, and
basic hosting (server + domain + TLS). Everything else is optional or not yet implemented.

Quick nav:

- [Payment gateways](#payment-gateways) (implemented)
- [Email / SMTP](#email--smtp) (required for OTP login)
- [Hosting & operations](#hosting--operations)
- [Self-hosted — no third party needed](#self-hosted--no-third-party-needed)
- [Optional integrations](#optional-integrations)
- [Not implemented — provider + code needed](#not-implemented--provider--code-needed)
- [Minimum launch checklist](#minimum-launch-checklist)

## Payment gateways

All drivers live in `apps/backend/Modules/Payment` and are wired end-to-end (hosted
checkout + webhook signature verification + tests). Enable a gateway by filling its env
vars in `apps/backend/.env.example` (lines ~101–181); gateways without credentials hide
themselves automatically via `isConfigured()`.

| Gateway | Env prefix | Notes |
|---|---|---|
| Stripe | `STRIPE_*` | Hosted Checkout; official SDK (`stripe/stripe-php`) |
| PayPal | `PAYPAL_*` | REST Orders v2; `PAYPAL_MODE=sandbox\|live` |
| Adyen | `ADYEN_*` | Pay by Link |
| Mercado Pago | `MERCADOPAGO_*` | LatAm focus; sandbox flag included |
| Checkout.com | `CHECKOUTCOM_*` | Payment Links |
| Razorpay | `RAZORPAY_*` | India focus; Payment Links |
| NowPayments | `NOWPAYMENTS_*` | Crypto payments; IPN callback signature |
| Manual | — | Bank transfer / offline; always available, no account needed |

Tips:

- **Local webhook testing:** expose the backend with ngrok or Cloudflared and point each
  gateway's webhook/notification URL at `POST /api/v1/payment/webhook/{driver}` (see
  `apps/backend/docs/flows/payment-flow.md`).
- **Iranian market gap:** despite the `fa` default locale, no Iranian gateway (Zarinpal,
  IDPay, Zibal, …) is implemented. Supporting Iran requires a new payment driver, not
  just an account.

## Email / SMTP

**Status:** `MAIL_MAILER=log` by default — OTP sign-in codes, order emails, support and
contact notifications all go to the log file. In dev, run the Mailpit profile
(`docker compose --profile mail up`, UI on port 8025, SMTP `mail:1025`).

**Needed for production:** one real provider. Any of these work over plain SMTP with
zero code changes (Laravel's stock config already has placeholders for the API-based
ones):

- Amazon SES — cheapest at volume
- Postmark — best deliverability for transactional email
- Mailgun / Resend / Brevo — solid mid-tier options
- Any SMTP relay your host provides

This is **blocking**: OTP sign-in currently has no other channel (see SMS below).

## Hosting & operations

Required to run the production stack (`docker-compose.prod.yml`):

| Item | Notes |
|---|---|
| VPS / dedicated server | Single Linux host; stack is self-contained |
| Domain + DNS | e.g. the live demo runs on `demo.baxela.com` / `admin-demo.baxela.com` |
| TLS reverse proxy | Deliberately outside compose — put Caddy, Traefik, nginx, or a cloud LB in front (HTTPS, HSTS, `X-Forwarded-Proto`). See `infrastructure/docker/production/README.md` |
| Container registry | Docker Hub / GHCR — only if CI should push images; currently GitHub Actions only builds and tests |
| GitHub Actions | Already the CI (`.github/workflows/ci.yml`); free tier is fine |
| Backups | Manual `mysqldump \| gzip` documented in the production README — consider a snapshot service or off-site cron |

## Self-hosted — no third party needed

These run as containers, no accounts or keys required:

- **MySQL 8** — database (prod and dev)
- **Redis 7** — cache + queue (prod, AOF enabled)
- **Laravel Reverb** — websockets for realtime notifications. `pusher-js` in the
  frontends is just the protocol client; Pusher.com / Ably cloud accounts are **not**
  needed.
- **Web Push (VAPID)** — browser notifications; keys are self-generated via
  `php artisan notification:generate-vapid-keys`.
- **Mailpit** — dev-only mail catcher.
- **nginx** — serves the API and the admin SPA.

## Optional integrations

- **Error monitoring (Sentry)** — implemented end-to-end in all three apps
  (`sentry/sentry-laravel`, `@sentry/nextjs`, `@sentry/react`) and **off by
  default**: with empty DSNs the SDKs stay disabled and no account is
  needed. Enable by setting `SENTRY_LARAVEL_DSN` (backend, runtime),
  `NEXT_PUBLIC_SENTRY_DSN` (storefront browser, build arg), `SENTRY_DSN`
  (storefront server, runtime) and `VITE_SENTRY_DSN` (admin, build arg).
  See [LOGGING.md](LOGGING.md) for capture policy, privacy notes and smoke
  tests. Alternatives (Bugsnag, Flare, GlitchTip) would need new integrations.
- **Object storage (S3-compatible)** — `AWS_*` env vars, the `s3` disk config, and the
  `S3` value in the media disk enum all exist, but the `league/flysystem-aws-s3` adapter
  is **not installed** and media code currently writes to the local `public` disk only.
  Options once enabled: AWS S3, Cloudflare R2, DigitalOcean Spaces, or self-hosted MinIO.
- **Google Fonts** — the admin panel loads Inter & Manrope from
  `fonts.googleapis.com` (`apps/admin/index.html`). Self-hosting the font files would
  remove this last external frontend dependency (the storefront CSP already forbids
  external fonts).

## Not implemented — provider + code needed

These features have no implementation; an account alone won't enable them:

| Feature | Current state | Candidate providers |
|---|---|---|
| SMS | None (one commented-out channel registration in the Notification module); OTP is email-only | Iran: Kavenegar, Ghasedak, SMS.ir — Global: Twilio, Vonage |
| Search engine | Catalog filtering is SQL-only; no Scout/Meilisearch/Algolia | Meilisearch (self-host), Algolia, Typesense |
| Social login | None; no Socialite | Google, GitHub, etc. |
| Shipping carrier APIs | Tracking numbers entered manually (`docs/shipping.md`) | FedEx, UPS, DHL, local couriers |
| Exchange rates | Multi-currency model has no FX rate source | exchangerate.host, Open Exchange Rates, ECB feeds |
| Newsletter / mailing list | Contact module stores subscribers in DB only | Mailchimp, Brevo, Listmonk (self-host) |

## Minimum launch checklist

1. **Server + domain + TLS proxy** — host the compose stack.
2. **One payment gateway account** — Stripe or PayPal for international; the Iranian
   market requires a new driver first.
3. **One email provider** — e.g. Postmark or SES; without it OTP sign-in and order
   emails don't actually send.
