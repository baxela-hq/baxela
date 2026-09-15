# GCP free-tier deployment (demo backend)

Runs the Baxela backend API on a single **always-free Google Cloud e2-micro
VM**, fronted by **Cloudflare**. This is a demo environment: admin and
storefront are NOT deployed here (they live on Cloudflare Pages), there is no
Redis and no TLS on the VM itself.

Stack on the VM: `docker-compose.gcp.yml` — backend (php-fpm) + nginx,
one-shot migrate, queue worker, scheduler, tuned MySQL 8. Deploy flow is the
same single-server model as `infrastructure/docker/production/README.md`.

## Staying free — the rules

The e2-micro Always Free tier ([cloud.google.com/free]) covers:

- **1 × e2-micro** (2 shared vCPU, 1 GB RAM) — only in `us-west1`,
  `us-central1`, `us-east1` (Oregon / Iowa / South Carolina)
- **30 GB-months of standard persistent disk** (pd-standard, not SSD)
- **1 external IPv4** attached to the qualifying instance (see
  [network pricing])
- ~200 GB/month network egress (excludes China/Australia)

What silently breaks "free":

- Creating the VM in any other region, or a second VM
- Resizing the boot disk beyond 30 GB or switching to pd-balanced/pd-ssd
- Leaving a reserved static IP unattached (billed at a higher rate)
- Extra disks, snapshots beyond 1 GB, load balancers, Cloud SQL (no free tier)

**Set a budget alert at $1** so any accidental charge emails you immediately:
Console → Billing → Budgets & alerts. A billing account is required even for
a $0 deployment.

## Prerequisites

- GCP project with billing enabled, `gcloud` installed and authenticated
  (`gcloud auth login`, `gcloud config set project <project-id>`)
- The repo is reachable from the VM (public remote, or a read-only deploy key:
  `gcloud compute ssh ... --tunnel-through-iap` then add the key to GitHub)

## First deploy

### 1. Create the VM (from your machine)

```bash
./infrastructure/gcp/create-vm.sh          # defaults to us-west1-b
```

Creates the e2-micro (Debian 12, 30 GB pd-standard), opens tcp:80, and
restricts SSH to Google's IAP range — there is no public SSH port.

### 2. Bootstrap the VM

```bash
gcloud compute ssh baxela-backend --zone=us-west1-b --tunnel-through-iap
```

On the VM:

```bash
sudo apt-get update && sudo apt-get install -y git
git clone <repo-url> /opt/baxela && cd /opt/baxela
sudo bash infrastructure/gcp/bootstrap.sh
```

`bootstrap.sh` is idempotent and installs Docker CE + compose plugin, a 2 GB
swapfile (required — image builds compile PHP extensions and MySQL needs
headroom on 1 GB), a journald size cap, and the nightly backup cron.

### 3. Configure and start the stack

```bash
cp .env.gcp.example .env.gcp
# edit .env.gcp: DB_PASSWORD, MYSQL_ROOT_PASSWORD (openssl rand -base64 24),
# and APP_URL if not using https://demo-api.baxela.com
```

Generate the app key through a one-off container (the image isn't built yet —
this first `run` also builds it, expect 5–10 minutes on the e2-micro):

```bash
docker compose --env-file .env.gcp -f docker-compose.gcp.yml run --rm migrate php artisan key:generate --show
# put the printed base64:... value into .env.gcp as APP_KEY, then:
docker compose --env-file .env.gcp -f docker-compose.gcp.yml up -d
```

Verify from your machine: `curl http://<VM_IP>/up` → `200 OK`.
Watch logs with `docker compose --env-file .env.gcp -f docker-compose.gcp.yml logs -f backend`.

### 4. Cloudflare (baxela.com zone)

- **DNS**: A record `demo-api` → VM external IP, **proxied** (orange cloud)
- **SSL/TLS mode: Flexible** — Cloudflare terminates HTTPS for visitors and
  connects to the VM over plain HTTP :80
- Why a flat subdomain: free Universal SSL covers `baxela.com` and
  `*.baxela.com` only, so `api.demo.baxela.com` would throw SSL errors unless
  you pay ~$10/mo for Advanced Certificate Manager
- The admin SPA (`demo-admin.baxela.com`, Cloudflare Pages) calls
  `https://demo-api.baxela.com/api/v1` with Bearer tokens — the backend's
  CORS (`allowed_origins: ['*']`, no credentials) already allows this
- Optional hardening later: issue a free Cloudflare Origin CA cert, mount it
  in nginx, and switch the zone to "Full (strict)"
- `APP_URL` in `.env.gcp` must match the public URL (`https://demo-api.baxela.com`)

Final check: `curl https://demo-api.baxela.com/up` → `200 OK`.

## Day-2 operations

All commands run on the VM, from the repo root (`/opt/baxela`).

**Update the demo** (after changes are merged to `main`):

```bash
git pull
docker compose --env-file .env.gcp -f docker-compose.gcp.yml up -d --build
```

The one-shot `migrate` service runs new migrations before fpm/queue/scheduler
restart, so workers never race migrations.

**Disk housekeeping** (the 30 GB disk fills with old images over time):

```bash
docker system df                          # check usage
docker image prune -af                    # remove unused images (safe: volumes untouched)
df -h /                                   # disk should stay well under 30 GB
```

Do NOT run `docker system prune --volumes` — it would delete `mysql-data`
and `backend-storage`.

**Logs**: `docker compose ... logs -f [service]` — Laravel logs go to stderr
(`LOG_CHANNEL=stderr`), so they land in the container log stream.

**Backups**: nightly at 03:15 via `/etc/cron.d/baxela-backup` → gzipped dump
in `/var/backups/baxela/`, 7-day rotation. Local disk only: if the VM or disk
is destroyed, the backups are gone.

**Restore a dump**:

```bash
cd /opt/baxela
gunzip -c /var/backups/baxela/baxela-<stamp>.sql.gz | \
  docker compose --env-file .env.gcp -f docker-compose.gcp.yml exec -T mysql \
  mysql -uroot -p"$MYSQL_ROOT_PASSWORD" baxela
```

## What's deliberately not on this VM

- **Admin / storefront** — Cloudflare Pages; the VM has no RAM for Node
  processes. Admin needs `VITE_API_BASE_URL=https://demo-api.baxela.com/api/v1`
  baked at build time.
- **Redis** — cache/queue/session use the database driver (the app defaults);
  saves ~50 MB RAM and one container.
- **TLS on the VM** — Cloudflare's edge handles HTTPS (Flexible mode).
- **Mail** — `MAIL_MAILER=log` placeholder, same as production.

## Resource notes (e2-micro, 1 GB RAM)

Rough steady-state: kernel + Docker ~250 MB, MySQL ~400 MB (tuned: 128 MB
buffer pool, performance_schema off, max 25 connections), php-fpm (2 workers)
~100 MB, queue + scheduler ~120 MB, nginx ~15 MB — tight but workable with
the 2 GB swapfile absorbing spikes. Per-service `mem_limit`s in
`docker-compose.gcp.yml` keep any single container from OOM-killing the rest.
CPU is a shared burstable core: expect slow image builds (~5–10 min) but
adequate request latency for a demo.

If the demo outgrows this: the same images and compose model run on any
bigger Compute Engine instance — only the machine type and the tuning flags
in `docker-compose.gcp.yml` change.

[cloud.google.com/free]: https://cloud.google.com/free
[network pricing]: https://cloud.google.com/vpc/network-pricing
