#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
RUNTIME_DIR="$ROOT_DIR/wp-harness/runtime"
PID_FILE="$RUNTIME_DIR/php-server.pid"

if [[ -f "$PID_FILE" ]]; then
  PID="$(cat "$PID_FILE")"
  if kill -0 "$PID" >/dev/null 2>&1; then
    kill "$PID"
    echo "Stopped WordPress server (PID $PID)."
  else
    echo "No running WordPress server found."
  fi
  rm -f "$PID_FILE"
else
  echo "No WordPress server PID file found."
fi
