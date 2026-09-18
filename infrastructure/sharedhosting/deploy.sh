#!/usr/bin/env bash
# Deploys the Baxela backend to a classic shared host (cPanel & co.) over
# rsync/SSH or FTP/SFTP (lftp). Runs ON YOUR MACHINE — nothing except the
# post-deploy commands executes on the host.
#
# Usage:
#   ./infrastructure/sharedhosting/deploy.sh [--init] [--dry-run]
#       [--skip-post-deploy] [--allow-public-delete]
#
#   --init                 first deploy: also render and upload the remote .env
#   --dry-run              build and stage for real, but print uploads instead
#                          of running them (rsync additionally runs with -n)
#   --skip-post-deploy     upload only; skip migrate/permissions/cache steps
#   --allow-public-delete  fixed-docroot mode only: also delete files inside
#                          the docroot that are no longer part of public/
#                          (off by default — the docroot may host other sites)
#
# Requires .env.sharedhosting at the repo root (copy .env.sharedhosting.example)
# and, for --init, a MySQL database + user already created in the hosting panel.
#
# The backend runs in shared-hosting mode (see apps/backend/docs/onboarding.md
# §5): QUEUE_CONNECTION=sync, CACHE_STORE=file — events run in-request, there
# are no workers. Backend only; admin and storefront keep their existing
# hosting. Full runbook: infrastructure/sharedhosting/README.md

set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BACKEND_DIR="$REPO_DIR/apps/backend"
CONF_FILE="$REPO_DIR/.env.sharedhosting"

die() { echo "ERROR: $*" >&2; exit 1; }
need() { command -v "$1" >/dev/null 2>&1 || die "$2"; }
show_help() { awk 'NR==1{next} /^#/{sub(/^# ?/, ""); print; next} {exit}' "${BASH_SOURCE[0]}"; }

# --- Args ----------------------------------------------------------------------

INIT=0
DRY_RUN=0
SKIP_POST_DEPLOY=0
ALLOW_PUBLIC_DELETE=0
for arg in "$@"; do
    case "$arg" in
        --init) INIT=1 ;;
        --dry-run) DRY_RUN=1 ;;
        --skip-post-deploy) SKIP_POST_DEPLOY=1 ;;
        --allow-public-delete) ALLOW_PUBLIC_DELETE=1 ;;
        -h|--help) show_help; exit 0 ;;
        *) die "unknown option: $arg (see --help)" ;;
    esac
done

# --- Config --------------------------------------------------------------------

if [ ! -f "$CONF_FILE" ]; then
    die "config not found: $CONF_FILE — copy .env.sharedhosting.example to .env.sharedhosting and fill it in"
fi
set -a
# shellcheck disable=SC1090
source "$CONF_FILE"
set +a

SHAREDHOSTING_PROTOCOL="${SHAREDHOSTING_PROTOCOL:-sftp}"
case "$SHAREDHOSTING_PROTOCOL" in
    rsync) : "${SHAREDHOSTING_PORT:=22}" ;;
    sftp)  : "${SHAREDHOSTING_PORT:=22}" ;;
    ftp)   : "${SHAREDHOSTING_PORT:=21}" ;;
    *) die "SHAREDHOSTING_PROTOCOL must be rsync, sftp or ftp (got '$SHAREDHOSTING_PROTOCOL')" ;;
esac

[ -n "${SHAREDHOSTING_HOST:-}" ] || die "SHAREDHOSTING_HOST is required in .env.sharedhosting"
[ -n "${SHAREDHOSTING_USER:-}" ] || die "SHAREDHOSTING_USER is required in .env.sharedhosting"

SHAREDHOSTING_REMOTE_DIR="${SHAREDHOSTING_REMOTE_DIR:-baxela}"
SHAREDHOSTING_REMOTE_DIR="${SHAREDHOSTING_REMOTE_DIR#/}"
SHAREDHOSTING_REMOTE_DIR="${SHAREDHOSTING_REMOTE_DIR%/}"
# The trailing slash makes ".." at the end matchable; reject hidden dirs and
# any traversal (., .., ../x, a/../b).
if [ -z "$SHAREDHOSTING_REMOTE_DIR" ] \
    || printf '%s/' "$SHAREDHOSTING_REMOTE_DIR" | grep -qE '^\.|(^|/)\.\./'; then
    die "SHAREDHOSTING_REMOTE_DIR must be a plain relative path below your home dir"
fi
case "$SHAREDHOSTING_REMOTE_DIR" in
    *[!A-Za-z0-9._/-]*) die "SHAREDHOSTING_REMOTE_DIR may only contain letters, digits, dot, dash, slash" ;;
esac

SHAREDHOSTING_DOCROOT_MODE="${SHAREDHOSTING_DOCROOT_MODE:-custom}"
case "$SHAREDHOSTING_DOCROOT_MODE" in
    custom|fixed) ;;
    *) die "SHAREDHOSTING_DOCROOT_MODE must be custom or fixed (got '$SHAREDHOSTING_DOCROOT_MODE')" ;;
esac

SHAREDHOSTING_PUBLIC_DIR="${SHAREDHOSTING_PUBLIC_DIR:-public_html}"
case "$SHAREDHOSTING_PUBLIC_DIR" in
    */*|""|.*|*[!A-Za-z0-9._-]*) die "SHAREDHOSTING_PUBLIC_DIR must be a single directory name (e.g. public_html)" ;;
esac

SHAREDHOSTING_PHP_BIN="${SHAREDHOSTING_PHP_BIN:-php}"
case "$SHAREDHOSTING_PHP_BIN" in
    *[!A-Za-z0-9._/+~-]*) die "SHAREDHOSTING_PHP_BIN may only contain a plain path or command name" ;;
esac

SHAREDHOSTING_APP_URL="${SHAREDHOSTING_APP_URL:-}"
if [ -n "$SHAREDHOSTING_APP_URL" ]; then
    case "$SHAREDHOSTING_APP_URL" in
        http://*|https://*) ;;
        *) die "SHAREDHOSTING_APP_URL must start with http:// or https://" ;;
    esac
    SHAREDHOSTING_APP_URL="${SHAREDHOSTING_APP_URL%/}"
fi

SSH_OPTS=()
SSH_KEY=""
case "$SHAREDHOSTING_PROTOCOL" in
    rsync)
        SSH_KEY="${SHAREDHOSTING_SSH_KEY:-$HOME/.ssh/id_rsa}"
        SSH_KEY="${SSH_KEY/#\~/$HOME}"
        [ -f "$SSH_KEY" ] || die "SSH key not found: $SSH_KEY — set SHAREDHOSTING_SSH_KEY in .env.sharedhosting"
        SSH_OPTS=(-p "$SHAREDHOSTING_PORT" -i "$SSH_KEY" -o BatchMode=yes -o StrictHostKeyChecking=accept-new)
        ;;
    sftp|ftp)
        [ -n "${SHAREDHOSTING_PASSWORD:-}" ] || die "SHAREDHOSTING_PASSWORD is required for protocol '$SHAREDHOSTING_PROTOCOL'"
        [ -n "$SHAREDHOSTING_APP_URL" ] || die "SHAREDHOSTING_APP_URL is required for protocol '$SHAREDHOSTING_PROTOCOL' (the post-deploy runner is called over HTTP)"
        if [ "$SHAREDHOSTING_PROTOCOL" = "ftp" ]; then
            echo "WARNING: plain FTP sends your password in cleartext — prefer sftp/rsync when the host allows it" >&2
        fi
        ;;
esac

if [ "$INIT" -eq 1 ]; then
    [ -n "$SHAREDHOSTING_APP_URL" ] || die "SHAREDHOSTING_APP_URL is required for --init"
    [ -n "${SHAREDHOSTING_DB_DATABASE:-}" ] || die "SHAREDHOSTING_DB_DATABASE is required for --init"
    [ -n "${SHAREDHOSTING_DB_USERNAME:-}" ] || die "SHAREDHOSTING_DB_USERNAME is required for --init"
    [ -n "${SHAREDHOSTING_DB_PASSWORD:-}" ] || die "SHAREDHOSTING_DB_PASSWORD is required for --init"
fi

# --- Preflight -----------------------------------------------------------------

need php "php is required on this machine (builds the production vendor tree)"
need composer "composer is required on this machine"
need rsync "rsync is required on this machine (staging is assembled with it)"
[ -f "$BACKEND_DIR/composer.lock" ] || die "composer.lock not found in apps/backend — run composer install locally first"
case "$SHAREDHOSTING_PROTOCOL" in
    rsync) need ssh "openssh client is required for protocol rsync" ;;
    sftp|ftp)
        need lftp "lftp is required for protocol '$SHAREDHOSTING_PROTOCOL' (dnf install lftp / apt install lftp)"
        if [ "$SKIP_POST_DEPLOY" -eq 0 ]; then
            need curl "curl is required to call the post-deploy runner"
            need openssl "openssl is required to mint the post-deploy runner token"
        fi
        ;;
esac

echo "==> Deploying the backend to ${SHAREDHOSTING_USER}@${SHAREDHOSTING_HOST}:${SHAREDHOSTING_PORT}"
echo "    protocol=${SHAREDHOSTING_PROTOCOL}  docroot-mode=${SHAREDHOSTING_DOCROOT_MODE}"
echo "    app=~/${SHAREDHOSTING_REMOTE_DIR}"
if [ "$SHAREDHOSTING_DOCROOT_MODE" = "fixed" ]; then
    echo "    docroot=~/${SHAREDHOSTING_PUBLIC_DIR} (public/ synced into it, index.php patched)"
else
    echo "    docroot=~/${SHAREDHOSTING_REMOTE_DIR}/public — point the (sub)domain at it in the panel"
fi
if [ "$DRY_RUN" -eq 1 ]; then
    echo "    DRY RUN — nothing will be uploaded; post-deploy steps are only printed"
fi

# On SSH-capable hosts, fail fast before uploading if the PHP behind
# SHAREDHOSTING_PHP_BIN is older than the composer.json requirement (^8.3).
if [ "$SHAREDHOSTING_PROTOCOL" = "rsync" ] && [ "$DRY_RUN" -eq 0 ]; then
    remote_php_check="${SHAREDHOSTING_PHP_BIN} -r 'exit(version_compare(PHP_VERSION, \"8.3.0\", \">=\") ? 0 : 1);'"
    ssh "${SSH_OPTS[@]}" "${SHAREDHOSTING_USER}@${SHAREDHOSTING_HOST}" "$remote_php_check" \
        || die "host PHP ($(SHAREDHOSTING_PHP_BIN)='${SHAREDHOSTING_PHP_BIN}') is older than 8.3 — pick a newer PHP in the panel"
fi

# --- Stage a production tree ---------------------------------------------------

echo "==> Staging a production tree (no dev files, no local state)"
STAGE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/baxela-sharedhosting.XXXXXX")"
cleanup() {
    if [ "${BAXELA_KEEP_STAGE:-0}" = "1" ]; then
        echo "==> Keeping staging dir (BAXELA_KEEP_STAGE=1): $STAGE_DIR"
    else
        rm -rf "$STAGE_DIR"
    fi
}
trap cleanup EXIT

cat > "$STAGE_DIR/excludes" <<'EOF'
# --- dev-only files (never uploaded)
.git
.gitignore
.gitattributes
.github
.idea
.editorconfig
AGENTS.md
README.md
docs
node_modules
vendor
tests
phpunit.xml
phpunit.xml.dist
pint.json
generate.sh
stubs
.phpunit.result.cache
# --- local state (dev values must not leak to the host)
.env
.env.*
database/database.sqlite
bootstrap/cache/*
# --- the public/storage symlink is re-created on the host by the post-deploy
public/storage
# --- runtime data ships as an empty directory skeleton only
storage/logs/*
storage/framework/cache/data/*
storage/framework/sessions/*
storage/framework/views/*
storage/framework/testing
storage/app/private/*
storage/app/public/*
EOF

rsync -a --exclude-from "$STAGE_DIR/excludes" "$BACKEND_DIR"/ "$STAGE_DIR/app/"

echo "==> Installing production dependencies (composer --no-dev, in the staging copy)"
(
    cd "$STAGE_DIR/app"
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress
)
[ -f "$STAGE_DIR/app/bootstrap/cache/packages.php" ] \
    || die "bootstrap/cache/packages.php missing after composer install — post-autoload-dump did not run"

if [ "$SHAREDHOSTING_DOCROOT_MODE" = "fixed" ]; then
    echo "==> Patching public/index.php for the fixed docroot (app lives at ../${SHAREDHOSTING_REMOTE_DIR})"
    sed -i "s|__DIR__.'/../|__DIR__.'/../${SHAREDHOSTING_REMOTE_DIR}/|g" "$STAGE_DIR/app/public/index.php"
    if ! grep -q "__DIR__.'/../${SHAREDHOSTING_REMOTE_DIR}/vendor/autoload.php" "$STAGE_DIR/app/public/index.php"; then
        die "index.php patch failed — the expected pattern was not found"
    fi
fi

echo "==> Rendering the remote .env (template: infrastructure/sharedhosting/env.app.example)"
APP_KEY_VALUE="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')" \
APP_URL_VALUE="$SHAREDHOSTING_APP_URL" \
DB_DATABASE_VALUE="${SHAREDHOSTING_DB_DATABASE:-}" \
DB_USERNAME_VALUE="${SHAREDHOSTING_DB_USERNAME:-}" \
DB_PASSWORD_VALUE="${SHAREDHOSTING_DB_PASSWORD:-}" \
php -r '
    $out = strtr(file_get_contents($argv[1]), [
        "{{APP_KEY}}" => getenv("APP_KEY_VALUE"),
        "{{APP_URL}}" => getenv("APP_URL_VALUE"),
        "{{DB_DATABASE}}" => getenv("DB_DATABASE_VALUE"),
        "{{DB_USERNAME}}" => getenv("DB_USERNAME_VALUE"),
        "{{DB_PASSWORD}}" => getenv("DB_PASSWORD_VALUE"),
    ]);
    file_put_contents($argv[2], $out);
' "$REPO_DIR/infrastructure/sharedhosting/env.app.example" "$STAGE_DIR/env.app"
if grep -q '{{' "$STAGE_DIR/env.app"; then
    die "unrendered placeholder left in the generated .env"
fi

echo "    staged tree: $(du -sh "$STAGE_DIR/app" | cut -f1)"

# --- Upload --------------------------------------------------------------------

if [ "$SHAREDHOSTING_PROTOCOL" = "rsync" ]; then
    RSYNC_BASE=(rsync -az --stats)
    RSYNC_RSH=(-e "ssh -p ${SHAREDHOSTING_PORT} -i ${SSH_KEY} -o BatchMode=yes -o StrictHostKeyChecking=accept-new")
    if [ "$DRY_RUN" -eq 1 ]; then
        RSYNC_BASE+=(-n)
    fi
    # Protect host-only state from --delete: the remote .env, runtime data in
    # storage/, artisan-generated caches, and the public/storage symlink.
    RSYNC_PROTECT=(--filter="P .env" --filter="P /storage/**" --filter="P /bootstrap/cache/**" --filter="P /public/storage")

    echo "==> Uploading the app tree (rsync + ssh)"
    "${RSYNC_BASE[@]}" --delete "${RSYNC_PROTECT[@]}" "${RSYNC_RSH[@]}" \
        "$STAGE_DIR/app/" "${SHAREDHOSTING_USER}@${SHAREDHOSTING_HOST}:${SHAREDHOSTING_REMOTE_DIR}/"

    if [ "$SHAREDHOSTING_DOCROOT_MODE" = "fixed" ]; then
        echo "==> Syncing public/ into ~/${SHAREDHOSTING_PUBLIC_DIR} (no --delete unless --allow-public-delete)"
        echo "    note: ${SHAREDHOSTING_PUBLIC_DIR}/.htaccess gets replaced by Laravel's — re-merge custom rules afterwards"
        pub=("${RSYNC_BASE[@]}" "${RSYNC_RSH[@]}" \
            "$STAGE_DIR/app/public/" "${SHAREDHOSTING_USER}@${SHAREDHOSTING_HOST}:${SHAREDHOSTING_PUBLIC_DIR}/")
        if [ "$ALLOW_PUBLIC_DELETE" -eq 1 ]; then
            pub+=(--delete --filter="P /.well-known/**")
        fi
        "${pub[@]}"
    fi

    if [ "$INIT" -eq 1 ]; then
        if ssh "${SSH_OPTS[@]}" "${SHAREDHOSTING_USER}@${SHAREDHOSTING_HOST}" "test -f '${SHAREDHOSTING_REMOTE_DIR}/.env'"; then
            echo "==> Remote .env already exists — leaving it alone (edit it in the panel)"
        else
            echo "==> Uploading the remote .env (first deploy)"
            "${RSYNC_BASE[@]}" "${RSYNC_RSH[@]}" "$STAGE_DIR/env.app" \
                "${SHAREDHOSTING_USER}@${SHAREDHOSTING_HOST}:${SHAREDHOSTING_REMOTE_DIR}/.env"
        fi
    fi
else
    LFTP_URL="${SHAREDHOSTING_PROTOCOL}://${SHAREDHOSTING_HOST}:${SHAREDHOSTING_PORT}"
    LFTP_SETTINGS="set net:max-retries 3; set net:timeout 30"
    if [ "$SHAREDHOSTING_PROTOCOL" = "ftp" ]; then
        LFTP_SETTINGS="${LFTP_SETTINGS}; set ftp:ssl-allow no"
    fi

    # Runs one lftp session. Critical commands append "|| exit N" so a failed
    # transfer aborts the session with that code (the trailing quit stays 0).
    run_lftp() {
        local cmds="$1"
        if [ "$DRY_RUN" -eq 1 ]; then
            echo "    [dry-run] lftp ${LFTP_URL} -u <user> --env-password"
            echo "    ${cmds}"
            return 0
        fi
        LFTP_PASSWORD="$SHAREDHOSTING_PASSWORD" lftp --env-password -u "$SHAREDHOSTING_USER" \
            -e "${LFTP_SETTINGS}; ${cmds}; quit" "$LFTP_URL"
    }

    remote_dir="$SHAREDHOSTING_REMOTE_DIR"

    echo "==> Uploading the app tree (lftp mirror)"
    # Empty dirs are not created by mirror — make the storage skeleton first.
    # mirror excludes keep host-only state safe from --delete: the remote .env,
    # runtime data under storage/, artisan-generated bootstrap/cache files and
    # the public/storage symlink. packages.php is uploaded explicitly below.
    run_lftp "mkdir -p ${remote_dir}/storage/logs; mkdir -p ${remote_dir}/storage/framework/cache/data; \
mkdir -p ${remote_dir}/storage/framework/sessions; mkdir -p ${remote_dir}/storage/framework/views; \
mkdir -p ${remote_dir}/bootstrap/cache; \
lcd '${STAGE_DIR}/app'; \
mirror -R --delete --no-perms --verbose=2 \
    --exclude-glob '.env' \
    --exclude-glob 'storage/*' \
    --exclude-glob 'bootstrap/cache/*' \
    --exclude-glob 'public/storage' \
    . '${remote_dir}' || exit 8; \
put 'bootstrap/cache/packages.php' -o '${remote_dir}/bootstrap/cache/packages.php' || exit 8"

    if [ "$SHAREDHOSTING_DOCROOT_MODE" = "fixed" ]; then
        echo "==> Syncing public/ into ~/${SHAREDHOSTING_PUBLIC_DIR} (no --delete unless --allow-public-delete)"
        echo "    note: ${SHAREDHOSTING_PUBLIC_DIR}/.htaccess gets replaced by Laravel's — re-merge custom rules afterwards"
        mirror_extra=""
        if [ "$ALLOW_PUBLIC_DELETE" -eq 1 ]; then
            mirror_extra="--delete --exclude-glob '.well-known/*'"
        fi
        run_lftp "lcd '${STAGE_DIR}/app/public'; \
mirror -R --no-perms --verbose=2 ${mirror_extra} . '${SHAREDHOSTING_PUBLIC_DIR}' || exit 8"
    fi

    if [ "$INIT" -eq 1 ]; then
        if [ "$DRY_RUN" -eq 1 ]; then
            echo "==> [dry-run] would upload the .env unless it already exists remotely"
        elif run_lftp "cls '${remote_dir}/.env' || exit 1"; then
            echo "==> Remote .env already exists — leaving it alone (edit it in the panel)"
        else
            echo "==> Uploading the remote .env (first deploy)"
            run_lftp "put '${STAGE_DIR}/env.app' -o '${remote_dir}/.env' || exit 8"
        fi
    fi
fi

# --- Post-deploy ---------------------------------------------------------------

if [ "$SKIP_POST_DEPLOY" -eq 1 ]; then
    echo "==> Skipping post-deploy (--skip-post-deploy)"
else
    if [ "$SHAREDHOSTING_PROTOCOL" = "rsync" ]; then
        echo "==> Post-deploy over SSH"
        if [ "$DRY_RUN" -eq 1 ]; then
            echo "    [dry-run] would run: chmod on storage+bootstrap/cache, migrate --force,"
            echo "    public/storage link, artisan optimize"
        else
            ssh "${SSH_OPTS[@]}" "${SHAREDHOSTING_USER}@${SHAREDHOSTING_HOST}" \
                "REMOTE_DIR='${SHAREDHOSTING_REMOTE_DIR}' PUBLIC_DIR='${SHAREDHOSTING_PUBLIC_DIR}' \
DOCROOT_MODE='${SHAREDHOSTING_DOCROOT_MODE}' PHP_BIN='${SHAREDHOSTING_PHP_BIN}' bash -s" <<'REMOTE'
set -e
echo "--- permissions"
chmod -R u+rwX,go+rX "$HOME/$REMOTE_DIR/storage" "$HOME/$REMOTE_DIR/bootstrap/cache"
echo "--- migrate"
cd "$HOME/$REMOTE_DIR"
"$PHP_BIN" artisan migrate --force
echo "--- public/storage link"
if [ "$DOCROOT_MODE" = "fixed" ]; then
    link="$HOME/$PUBLIC_DIR/storage"
    if [ -e "$link" ] && [ ! -L "$link" ]; then
        echo "ERROR: $link exists and is not a symlink — remove it manually" >&2
        exit 1
    fi
    rm -f "$link"
    ln -s "../$REMOTE_DIR/storage/app/public" "$link"
    echo "    $link -> ../$REMOTE_DIR/storage/app/public"
else
    "$PHP_BIN" artisan storage:link
fi
echo "--- caches (non-fatal)"
if ! "$PHP_BIN" artisan optimize; then
    echo "WARNING: optimize failed — the app still works, just uncached"
fi
REMOTE
        fi
    else
        echo "==> Post-deploy via temporary token-protected runner"
        token="$(openssl rand -hex 32)"
        runner_name="baxela-post-deploy-$(openssl rand -hex 8).php"
        runner_local="$STAGE_DIR/$runner_name"
        if [ "$SHAREDHOSTING_DOCROOT_MODE" = "fixed" ]; then
            app_relative="../${SHAREDHOSTING_REMOTE_DIR}"
            runner_remote_dir="$SHAREDHOSTING_PUBLIC_DIR"
        else
            app_relative=".."
            runner_remote_dir="${SHAREDHOSTING_REMOTE_DIR}/public"
        fi
        sed -e "s|{{TOKEN}}|$token|" \
            -e "s|{{DOCROOT_MODE}}|$SHAREDHOSTING_DOCROOT_MODE|" \
            -e "s|{{APP_RELATIVE}}|$app_relative|" \
            "$REPO_DIR/infrastructure/sharedhosting/post-deploy.php" > "$runner_local"

        if [ "$DRY_RUN" -eq 1 ]; then
            echo "    [dry-run] would upload $runner_name to ${runner_remote_dir}/ and call"
            echo "    ${SHAREDHOSTING_APP_URL}/$runner_name?token=<hidden>&cleanup=1"
        else
            run_lftp "put '${runner_local}' -o '${runner_remote_dir}/${runner_name}' || exit 8"
            runner_url="${SHAREDHOSTING_APP_URL}/${runner_name}?token=${token}&cleanup=1"
            echo "    calling ${SHAREDHOSTING_APP_URL}/${runner_name} (token-gated, self-deletes)"
            runner_output="$(curl -sS -L --max-time 600 "$runner_url")" \
                || die "could not reach the post-deploy runner — check SHAREDHOSTING_APP_URL and DNS"
            echo "$runner_output"
            echo "$runner_output" | grep -q 'DEPLOY_RESULT: OK' \
                || die "the post-deploy runner reported failure (see its output above)"
            if run_lftp "cls '${runner_remote_dir}/${runner_name}' || exit 1"; then
                run_lftp "rm -f '${runner_remote_dir}/${runner_name}'"
                if run_lftp "cls '${runner_remote_dir}/${runner_name}' || exit 1"; then
                    echo "WARNING: could not delete ${runner_name} from ${runner_remote_dir} — remove it in the panel's file manager!" >&2
                fi
            fi
        fi
    fi
fi

echo
echo "Deploy done."
cat <<EOF
Notes:
  - Scheduler (optional): add this cron entry in the panel
      * * * * * cd \$HOME/${SHAREDHOSTING_REMOTE_DIR} && ${SHAREDHOSTING_PHP_BIN} artisan schedule:run >> /dev/null 2>&1
  - The remote .env lives at ~/${SHAREDHOSTING_REMOTE_DIR}/.env — edit it in the
    panel's file manager; deploys never overwrite it.
  - Opcache on shared hosts may briefly serve stale code after a deploy.
  - Backend only: admin and storefront keep their existing hosting.
EOF
