#!/usr/bin/env bash
set -euo pipefail

NETWORK="${WP_ENV_DOCKER_NETWORK:-dev-shared}"

if ! command -v docker >/dev/null 2>&1; then
  echo "wp-env: docker not found in PATH; skipping network attach." >&2
  exit 0
fi

if ! docker network inspect "$NETWORK" >/dev/null 2>&1; then
  echo "wp-env: docker network '$NETWORK' not found; skipping network attach." >&2
  exit 0
fi

attach_container() {
  local container_id="$1"
  local alias_name="${2:-}"
  if [[ -z "$container_id" ]]; then
    return
  fi
  if [[ -n "$alias_name" ]]; then
    docker network connect --alias "$alias_name" "$NETWORK" "$container_id" >/dev/null 2>&1 || true
  else
    docker network connect "$NETWORK" "$container_id" >/dev/null 2>&1 || true
  fi
}

wordpress_ids="$(docker ps --filter "label=com.docker.compose.service=wordpress" --format '{{.ID}}')"
for id in $wordpress_ids; do
  attach_container "$id" "wp.dev.local"
done

db_ids="$(docker ps --filter "label=com.docker.compose.service=mysql" --format '{{.ID}}')"
if [[ -z "$db_ids" ]]; then
  db_ids="$(docker ps --filter "label=com.docker.compose.service=mariadb" --format '{{.ID}}')"
fi

for id in $db_ids; do
  attach_container "$id" "wordpress-db"
done
