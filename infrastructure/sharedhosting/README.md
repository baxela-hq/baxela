# Shared-hosting deployment (backend only)

Deploys the Baxela backend to a classic shared host (cPanel, DirectAdmin,
Plesk & co.) over **rsync/SSH** or **FTP/SFTP (lftp)** — for testing and light
use. Admin and storefront are **not** deployed here; they keep their existing
hosting.

The app runs in the shared-hosting mode documented in
`apps/backend/docs/onboarding.md` §5: `QUEUE_CONNECTION=sync`,
`CACHE_STORE=file` — events run in-request and there are no workers. That is
the deliberate trade-off for running an event-driven modular monolith without
a shell: slightly slower responses instead of a queue worker.

## What the deploy script does

`deploy.sh` runs **on your machine**:

1. Stages a production tree in a temp dir (no `.git`, `node_modules`, `tests`,
   dev `.env`, local caches/logs — runtime dirs ship as an empty skeleton).
2. Runs `composer install --no-dev --optimize-autoloader` **inside the
   staging copy**, so your working `vendor/` (Pest & co.) is never touched.
3. Uploads the tree with `rsync --delete` (SSH) or `lftp mirror -R --delete`
   (FTP/SFTP) — incremental, so only changed files transfer after the first
   deploy. Host-only state is protected from deletion: the remote `.env`,
   everything under `storage/`, artisan caches in `bootstrap/cache/`, and the
   `public/storage` symlink.
4. With `--init` (first deploy): renders `.env` from
   `env.app.example` (APP_KEY generated, DB values and APP_URL injected,
   shared-hosting mode pinned) and uploads it. **Later deploys never overwrite
   the remote `.env`** — edit it in the panel's file manager.
5. Post-deploy: fixes permissions on `storage/` + `bootstrap/cache/`, runs
   `migrate --force`, creates the `public/storage` link, `artisan optimize`.
   Over SSH these run directly; without SSH they run through a temporary
   token-protected PHP runner (see below).

## Prerequisites

On the host (check in the panel):

- **PHP ≥ 8.3** pinned for the (sub)domain, with the usual Laravel extensions
  (`pdo_mysql`, `mbstring`, `openssl`, …`zip`/`fileinfo` if you use them)
- A MySQL database + user (shared hosts serve MySQL on `localhost`)
- Ideally: a dedicated subdomain (e.g. `api.example.com`) so the docroot can
  be dedicated to the backend

On your machine: `php`, `composer`, `rsync`, plus `ssh` (rsync protocol) or
`lftp` (`dnf install lftp` / `apt install lftp`), and `curl` + `openssl` when
using the no-SSH post-deploy runner.

> The staged `vendor/` is built by your local PHP; the host must not run an
> older PHP than the one you build with (the script enforces ≥ 8.3 over SSH).

## Setup

```bash
cp .env.sharedhosting.example .env.sharedhosting
$EDITOR .env.sharedhosting     # host, user, credentials, remote dirs, app URL
```

Transport choice:

| Protocol | When | Notes |
|---|---|---|
| `rsync` | host offers SSH | Best: fast incremental sync, post-deploy over SSH. Needs an accepted SSH key; the host must provide `rsync` + `bash`. |
| `sftp` | most cPanel hosts | lftp over SFTP. On first use, connect once interactively (`lftp sftp://user@host`) to accept the host key. |
| `ftp` | plain FTP only | Works, but the password travels in cleartext — avoid if the host offers SFTP. |

Docroot mode:

- **`custom`** (preferred): the panel lets you point the (sub)domain's
  document root at `~/<REMOTE_DIR>/public`. The app is served exactly as
  designed; nothing is patched.
- **`fixed`**: the docroot is an untouchable `public_html`. The script keeps
  the app at `~/<REMOTE_DIR>` (outside the docroot) and syncs `public/` into
  `public_html/` with a patched `index.php`. Nothing inside `public_html` is
  ever deleted unless you pass `--allow-public-delete`. Note that a
  pre-existing `public_html/.htaccess` gets overwritten — re-merge custom
  rules afterwards.

## First deploy

```bash
# Rehearse: builds + stages for real, prints every upload instead of running it
./infrastructure/sharedhosting/deploy.sh --dry-run

# The real thing (uploads .env, code, then post-deploy)
./infrastructure/sharedhosting/deploy.sh --init
```

In `custom` mode, point the (sub)domain's docroot at `~/<REMOTE_DIR>/public`
in the panel before (or right after) the first deploy.

Verify:

```bash
curl -s -H 'Accept: application/json' https://api.example.com/api/v1/does-not-exist
# A JSON 404 from Laravel proves the app booted (routes, .env, vendor, PHP).
```

## Subsequent deploys

```bash
./infrastructure/sharedhosting/deploy.sh
```

Syncs changed files, then migrates and re-optimizes. The remote `.env` and
everything under `storage/` (logs, cache, uploaded files) are untouched.

## The scheduler (optional)

Some modules register scheduled tasks (e.g. order expiry in
`Modules/Order`). To run them, add a cron entry in the panel (Cron Jobs →
every minute):

```
* * * * * cd $HOME/<REMOTE_DIR> && php artisan schedule:run >> /dev/null 2>&1
```

Replace `php` with the panel's PHP binary path if the shell default differs
(e.g. `/opt/cpanel/ea-php84/root/usr/bin/php`).

## Post-deploy without SSH (the token-protected runner)

On `sftp`/`ftp` hosts there is no shell, so the script temporarily uploads
`post-deploy.php` under a random name with a one-time 64-hex-char token baked
in, calls it once over HTTPS (`?token=…&cleanup=1`), checks the
`DEPLOY_RESULT: OK` marker, and deletes it from the server (the runner also
unlinks itself). Without the token it answers 404. It executes a fixed
sequence only — permissions, `migrate --force`, the `public/storage` link,
`optimize` — and can never run arbitrary commands.

## Troubleshooting

- **HTTP 500 / white page** — check `~/<REMOTE_DIR>/storage/logs/laravel.log`
  in the panel's file manager. Usual suspects: PHP version < 8.3 for the
  domain, wrong DB credentials in the remote `.env`, unwritable `storage/`.
- **Permissions errors** — the post-deploy step chmods `storage/` and
  `bootstrap/cache/`; if the host's PHP runs as a different user than your
  FTP login, fix ownership via the panel (or support ticket).
- **Browsing `/` in a browser errors** — expected: this is an API backend
  (`api/v1/...`); the scaffold welcome view references Vite assets that are
  not built. Use the API, not the root page.
- **Stale code after a deploy** — opcache: shared hosts usually roll it over
  within a minute; some panels let you reset PHP-FPM manually.
- **`storage:link` fails** — a few hosts disallow symlinks; the runner will
  report it. Public uploads then need the `public/storage` path configured
  differently (or the host switched).
- **rsync protocol, "rsync: command not found" on the host** — the host lacks
  `rsync`; switch `SHAREDHOSTING_PROTOCOL` to `sftp`.
- **Debugging the staged tree** — `BAXELA_KEEP_STAGE=1 ./infrastructure/...deploy.sh --dry-run`
  keeps the staging dir and prints its path; it is a fully bootable Laravel
  tree you can test locally with `php -S`.

## Limits (by design)

- Testing / light use: sync queue means listeners and listeners-of-listeners
  run inside the HTTP request.
- No horizon-style workers, no Redis, no horizontal scale.
- The storefront (Next.js SSR) cannot run on shared hosting at all.
