#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
HARNESS_DIR="$ROOT_DIR/wp-harness"
RUNTIME_DIR="$HARNESS_DIR/runtime"
WP_DIR="$HARNESS_DIR/wordpress"

PORT="${WP_PORT:-8888}"
PID_FILE="$RUNTIME_DIR/php-server.pid"
LOG_FILE="$RUNTIME_DIR/php-server.log"

mkdir -p "$RUNTIME_DIR"

if [[ -f "$PID_FILE" ]] && kill -0 "$(cat "$PID_FILE")" >/dev/null 2>&1; then
  echo "WordPress is already running (PID $(cat "$PID_FILE"))."
  exit 0
fi

"$HARNESS_DIR/bin/bootstrap-wordpress.sh"

php -S 0.0.0.0:"$PORT" -t "$WP_DIR" >"$LOG_FILE" 2>&1 &

echo $! > "$PID_FILE"

echo "WordPress running at http://localhost:${PORT}"
