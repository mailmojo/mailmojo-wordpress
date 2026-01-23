#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
HARNESS_DIR="$ROOT_DIR/wp-harness"
WP_DIR="$HARNESS_DIR/wordpress"
DB_DIR="$WP_DIR/wp-content/database"
DB_FILE="$DB_DIR/mailmojo.sqlite"

"$HARNESS_DIR/bin/stop-wordpress.sh" >/dev/null 2>&1 || true

if [[ -f "$DB_FILE" ]]; then
  rm -f "$DB_FILE"
fi

if [[ -f "$WP_DIR/wp-config.php" ]]; then
  rm -f "$WP_DIR/wp-config.php"
fi

"$HARNESS_DIR/bin/bootstrap-wordpress.sh"

echo "WordPress reset complete."
