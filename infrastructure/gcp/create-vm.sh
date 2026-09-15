#!/usr/bin/env bash
# Create the always-free GCP VM for the Baxela demo backend, plus its
# firewall rules. Run this from YOUR machine (needs gcloud installed and
# authenticated: `gcloud auth login` + `gcloud config set project <project>`).
#
# Free-tier constraints baked into these flags — do not change them without
# re-reading infrastructure/gcp/README.md ("Staying free"):
#   - machine-type e2-micro: the only always-free instance type
#   - zone us-west1-*: free tier covers us-west1, us-central1, us-east1 only
#   - boot-disk pd-standard, 30GB: the monthly free disk allowance
#
# Usage:
#   ./infrastructure/gcp/create-vm.sh [zone]        # default: us-west1-b

set -euo pipefail

ZONE="${1:-us-west1-b}"
INSTANCE_NAME="baxela-backend"
REGION="${ZONE%-*}" # us-west1-b -> us-west1

case "$REGION" in
    us-west1 | us-central1 | us-east1) ;;
    *)
        echo "ERROR: zone '$ZONE' is outside the free-tier regions" >&2
        echo "       (us-west1, us-central1, us-east1). The e2-micro free" >&2
        echo "       tier does NOT apply there and you WILL be billed." >&2
        exit 1
        ;;
esac

echo "==> Creating VM ${INSTANCE_NAME} in ${ZONE} (this takes ~30s)"
gcloud compute instances create "$INSTANCE_NAME" \
    --zone="$ZONE" \
    --machine-type=e2-micro \
    --image-family=debian-12 \
    --image-project=debian-cloud \
    --boot-disk-size=30GB \
    --boot-disk-type=pd-standard \
    --tags=baxela-api

echo "==> Firewall: HTTP (80) open to the world (Cloudflare proxies here)"
if ! gcloud compute firewall-rules describe allow-baxela-http --format='value(name)' &>/dev/null; then
    gcloud compute firewall-rules create allow-baxela-http \
        --network=default \
        --allow=tcp:80 \
        --source-ranges=0.0.0.0/0 \
        --target-tags=baxela-api \
        --description="HTTP for the Baxela demo API (Cloudflare -> origin)"
else
    echo "    allow-baxela-http already exists, skipping"
fi

echo "==> Firewall: SSH (22) only from Google's IAP range — no public SSH"
# Connect afterwards with: gcloud compute ssh baxela-backend --zone=$ZONE --tunnel-through-iap
if ! gcloud compute firewall-rules describe allow-baxela-ssh-iap --format='value(name)' &>/dev/null; then
    gcloud compute firewall-rules create allow-baxela-ssh-iap \
        --network=default \
        --allow=tcp:22 \
        --source-ranges=35.235.240.0/20 \
        --target-tags=baxela-api \
        --description="SSH to Baxela VM via IAP tunnel only"
else
    echo "    allow-baxela-ssh-iap already exists, skipping"
fi

EXTERNAL_IP="$(gcloud compute instances describe "$INSTANCE_NAME" \
    --zone="$ZONE" --format='get(networkInterfaces[0].accessConfigs[0].natIP)')"

cat <<EOF

Done. Next steps (see infrastructure/gcp/README.md for the full runbook):

  1. SSH via IAP:
       gcloud compute ssh ${INSTANCE_NAME} --zone=${ZONE} --tunnel-through-iap
  2. On the VM, bootstrap Docker/swap:
       sudo bash /path/to/repo/infrastructure/gcp/bootstrap.sh
  3. Cloudflare: proxied A record  demo-api.baxela.com -> ${EXTERNAL_IP}

VM external IP: ${EXTERNAL_IP}
EOF
