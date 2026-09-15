#!/usr/bin/env bash
# One-time bootstrap for the GCP free-tier VM (Debian 12, e2-micro, 1 GB RAM).
# Run ON THE VM as root:  sudo bash infrastructure/gcp/bootstrap.sh
#
# Idempotent: safe to re-run (e.g. after cloning a fresh copy of the repo).
# Installs Docker + compose plugin, creates swap (mandatory for on-VM image
# builds and MySQL headroom on a 1 GB box), caps journald to protect the
# 30 GB disk, and installs the nightly backup cron.

set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BACKUP_DIR=/var/backups/baxela
SWAP_SIZE=2G

echo "==> Installing Docker CE + compose plugin + git + unattended-upgrades"
apt-get update -qq
apt-get install -y -qq ca-certificates curl git unattended-upgrades >/dev/null

install -m 0755 -d /etc/apt/keyrings
if [ ! -f /etc/apt/keyrings/docker.gpg ]; then
    curl -fsSL https://download.docker.com/linux/debian/gpg \
        -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
fi
if ! grep -qs 'download.docker.com' /etc/apt/sources.list.d/docker.list; then
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
https://download.docker.com/linux/debian $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
        >/etc/apt/sources.list.d/docker.list
fi
apt-get update -qq
apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin >/dev/null

echo "==> Creating ${SWAP_SIZE} swapfile (kernel has no swap on GCP images)"
# On-VM docker builds compile PHP extensions and MySQL needs headroom —
# without swap the OOM killer takes down containers on a 1 GB box.
if [ ! -s /swapfile ]; then
    fallocate -l "$SWAP_SIZE" /swapfile
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
    swapon /swapfile
    grep -qs '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >>/etc/fstab
else
    echo "    /swapfile already exists, skipping"
fi
sysctl -w vm.swappiness=20 >/dev/null
grep -q '^vm.swappiness' /etc/sysctl.conf \
    || echo 'vm.swappiness=20' >>/etc/sysctl.conf

echo "==> Capping journald at 200M (30GB disk protection)"
install -d /etc/systemd/journald.conf.d
cat >/etc/systemd/journald.conf.d/99-size-cap.conf <<'EOF'
[Journal]
SystemMaxUse=200M
RuntimeMaxUse=100M
EOF
systemctl restart systemd-journald

echo "==> Installing nightly MySQL backup cron (local disk, 7-day rotation)"
install -d "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
install -m 0755 "${REPO_DIR}/infrastructure/gcp/backup.sh" /usr/local/bin/baxela-backup
cat >/etc/cron.d/baxela-backup <<EOF
# Nightly MySQL dump for the Baxela demo backend (installed by bootstrap.sh)
SHELL=/bin/bash
15 3 * * * root COMPOSE_DIR='${REPO_DIR}' /usr/local/bin/baxela-backup
EOF
chmod 0644 /etc/cron.d/baxela-backup

echo
echo "Bootstrap done. Next: cp .env.gcp.example .env.gcp, fill it in, then"
echo "  docker compose --env-file .env.gcp -f docker-compose.gcp.yml up -d --build"
echo "(run from ${REPO_DIR})"
