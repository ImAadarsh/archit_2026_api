#!/usr/bin/env bash
# Deploy ONLY the product-review API files to invoicemate.in
# Usage:
#   export DEPLOY_SSH_PASS='your-password'   # optional if using ssh keys
#   ./deploy-review-api.sh
#
# Or: DEPLOY_SSH_PASS='...' ./deploy-review-api.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOCAL_API_ROOT="$SCRIPT_DIR"

SSH_HOST="${DEPLOY_SSH_HOST:-u262009927@145.79.213.132}"
SSH_PORT="${DEPLOY_SSH_PORT:-65002}"
REMOTE_API="${DEPLOY_REMOTE_API:-/home/u262009927/domains/invoicemate.in/public_html/api}"

# Only these paths (relative to api/). Nothing else is uploaded.
FILES=(
  "app/Http/Controllers/ReviewController.php"
  "app/Models/ShopProductReview.php"
  "app/Models/ShopProductReviewImage.php"
  "routes/api.php"
)

ssh_cmd() {
  if [[ -n "${DEPLOY_SSH_PASS:-}" ]] && command -v sshpass >/dev/null 2>&1; then
    sshpass -e ssh -p "$SSH_PORT" -o StrictHostKeyChecking=accept-new "$@"
  else
    ssh -p "$SSH_PORT" -o StrictHostKeyChecking=accept-new "$@"
  fi
}

scp_cmd() {
  if [[ -n "${DEPLOY_SSH_PASS:-}" ]] && command -v sshpass >/dev/null 2>&1; then
    sshpass -e scp -P "$SSH_PORT" -o StrictHostKeyChecking=accept-new "$@"
  else
    scp -P "$SSH_PORT" -o StrictHostKeyChecking=accept-new "$@"
  fi
}

echo "==> Deploying review API files to ${SSH_HOST}:${REMOTE_API}"
echo "    Files (${#FILES[@]}):"
for f in "${FILES[@]}"; do
  echo "      - $f"
done

# Ensure remote dirs exist + review_images storage
ssh_cmd "$SSH_HOST" "mkdir -p \
  '${REMOTE_API}/app/Http/Controllers' \
  '${REMOTE_API}/app/Models' \
  '${REMOTE_API}/routes' \
  '${REMOTE_API}/storage/app/public/review_images' && \
  chmod -R ug+rwX '${REMOTE_API}/storage/app/public/review_images' 2>/dev/null || true"

for f in "${FILES[@]}"; do
  local_path="${LOCAL_API_ROOT}/${f}"
  if [[ ! -f "$local_path" ]]; then
    echo "ERROR: missing local file: $local_path" >&2
    exit 1
  fi
  remote_path="${REMOTE_API}/${f}"
  echo "→ $f"
  scp_cmd "$local_path" "${SSH_HOST}:${remote_path}"
done

echo "==> Verifying on server…"
ssh_cmd "$SSH_HOST" "ls -la \
  '${REMOTE_API}/app/Http/Controllers/ReviewController.php' \
  '${REMOTE_API}/app/Models/ShopProductReview.php' \
  '${REMOTE_API}/app/Models/ShopProductReviewImage.php' \
  '${REMOTE_API}/routes/api.php' && \
  grep -n 'reviews' '${REMOTE_API}/routes/api.php' | head -10"

echo "==> Done. Only the ${#FILES[@]} review-related files were updated."
