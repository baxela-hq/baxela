# Telegram Notifications

Delivery channel in the `Notification` module that sends order/payment
notifications to a user's Telegram chat through the store's bot, plus the
bot conversation that links accounts. The same bot and the same linking
flow serve customers (storefront → profile → settings) and staff (admin →
settings → notifications).

## How it works

```
User                Telegram            Backend
 │  /start ─────────►│                   │
 │                   │──── update ──────►│  POST /api/v1/notification/webhook/telegram
 │                   │                   │  cache code → chat_id (10 min)
 │◄─ code reply ─────│◄── sendMessage ───│
 │                                       │
 │  enter code in settings ─────────────►│  POST /api/v1/notification/user/telegram/link
 │                                       │  chat linked to the account
 │◄─ order notification ─────────────────│  TelegramChannel → sendMessage
```

- **Bot account**: registered once with [@BotFather](https://t.me/BotFather) — it
  issues the bot token. The bot's logic (conversation + delivery) lives in
  `Modules/Notification`.
- **Linking**: `/start` replies with a single-use 6-digit code (10-minute
  expiry). Entering it in the app links the chat. Entering the numeric
  Telegram ID directly also works — the backend verifies it by actually
  delivering the confirmation message (bots cannot message users who never
  started the chat).
- **One row per account and per chat**: re-linking a chat moves it to the
  new account; re-linking an account moves it to a new chat.
- **Blocking the bot**: a `403 Forbidden` from Telegram (or a
  `my_chat_member: kicked` update) deletes the subscription — same policy
  as expired web-push endpoints.
- **Per-user preferences**: telegram appears alongside database/email/webpush
  in the admin notification-preference matrix (it is routed wherever webpush
  is routed, see `Modules/Notification/config/notifications.php`).

## Setup

1. **Create the bot** — message [@BotFather](https://t.me/BotFather):
   `/newbot`, pick a name and username, copy the token.
2. **Configure env** (backend `.env`, or `.env.production` for the prod stack):

   ```env
   TELEGRAM_BOT_TOKEN=123456:ABC-DEF...     # from BotFather
   TELEGRAM_WEBHOOK_SECRET=<random string>  # e.g. openssl rand -hex 16
   ```

   Without a token the channel no-ops safely (log warning), like webpush
   without VAPID keys.
3. **Register the webhook** (production — needs public HTTPS on 443/80/88/8443):

   ```bash
   php artisan notification:telegram-webhook          # set (uses APP_URL)
   php artisan notification:telegram-webhook --remove # delete
   ```

   Every update Telegram POSTs then carries the `X-Telegram-Bot-Api-Secret-Token`
   header, verified against `TELEGRAM_WEBHOOK_SECRET` (403 otherwise).
4. **Local development** — no public URL needed; run the poll command instead
   (Telegram allows only one delivery mode, so remove the webhook first if
   one is registered):

   ```bash
   php artisan notification:telegram-webhook --remove   # if needed
   php artisan notification:telegram-poll               # long-polls getUpdates
   ```

## Bot commands

| Command   | Behavior                                                        |
| --------- | --------------------------------------------------------------- |
| `/start`  | Replies with a fresh linking code + the chat's Telegram ID      |
| `/unlink` (or `/stop`) | Unlinks the chat and confirms                    |
| `/help`   | Lists the commands                                              |

Replies are localized per chat (`fa` when the user's Telegram client
reports a `fa*` language, English otherwise); strings live in
`Modules/Notification/lang/{en,fa}/telegram.php`.

## API surface

| Method   | Endpoint                                        | Auth      | Purpose                          |
| -------- | ----------------------------------------------- | --------- | -------------------------------- |
| `POST`   | `/api/v1/notification/webhook/telegram`         | secret header | Bot updates (Telegram → app)  |
| `GET`    | `/api/v1/notification/telegram/bot-info`        | public    | Bot username + `t.me` deep link  |
| `GET`    | `/api/v1/notification/user/telegram/subscription` | user    | Linked chat, or null             |
| `POST`   | `/api/v1/notification/user/telegram/link`       | user      | Link by `{code}` or `{chat_id}`  |
| `DELETE` | `/api/v1/notification/user/telegram/subscription` | user    | Unlink                           |
| `POST`   | `/api/v1/notification/user/telegram/test`       | user      | Send a test message              |

Bruno requests live in `api/bruno/Notification/{Public,User,Webhook}/Telegram`.
