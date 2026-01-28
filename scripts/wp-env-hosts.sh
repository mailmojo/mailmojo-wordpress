#!/usr/bin/env bash
set -euo pipefail

HOSTNAME_TO_ADD="${1:-api.mailmojo.local}"

WP_CONTAINER="$(
  docker ps --format '{{.Names}}' \
  | grep -E 'wordpress-[0-9]+$' \
  | grep -v -- '-tests-' \
  | head -n 1
)"

if [ -z "$WP_CONTAINER" ]; then
  echo "Could not find wp-env WordPress container (non-tests)." >&2
  exit 1
fi

docker exec -u root -i "$WP_CONTAINER" sh -lc "
  set -e

  ip=\$(awk '/host\\.docker\\.internal/ {print \$1}' /etc/hosts | grep -E '^[0-9]+\\.[0-9]+\\.[0-9]+\\.[0-9]+$' | head -n 1)

  if [ -z \"\$ip\" ]; then
    echo 'ERROR: Could not determine IPv4 for host.docker.internal inside container.' >&2
    echo 'Current host.docker.internal entries in /etc/hosts:' >&2
    grep 'host.docker.internal' /etc/hosts >&2 || true
    exit 1
  fi

  # Remove any previous entries for the hostname (avoid duplicates / stale IPs)
  sed -i '/[[:space:]]$HOSTNAME_TO_ADD\$/d' /etc/hosts 2>/dev/null || true

  echo \"\$ip $HOSTNAME_TO_ADD\" >> /etc/hosts
  echo \"Patched: \$ip $HOSTNAME_TO_ADD\"

  # Show what we ended up with
  tail -n 5 /etc/hosts
"