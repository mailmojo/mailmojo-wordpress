#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
HARNESS_DIR="$ROOT_DIR/wp-harness"
WP_DIR="$HARNESS_DIR/wordpress"
RUNTIME_DIR="$HARNESS_DIR/runtime"

PORT="${WP_PORT:-8888}"
URL="http://localhost:${PORT}"

if ! command -v wp >/dev/null 2>&1; then
  echo "wp-cli is required but was not found in PATH." >&2
  exit 1
fi

mkdir -p "$WP_DIR" "$RUNTIME_DIR"

if [[ ! -f "$WP_DIR/wp-load.php" ]]; then
  wp core download --path="$WP_DIR" --quiet
fi

mkdir -p "$WP_DIR/wp-content/database" "$WP_DIR/wp-content/plugins"

SQLITE_DROPIN_DEFAULT="/opt/tools/wp-sqlite-db/db.php"
SQLITE_DROPIN="${WP_SQLITE_DROPIN:-$SQLITE_DROPIN_DEFAULT}"
if [[ ! -f "$WP_DIR/wp-content/db.php" ]]; then
  if [[ ! -f "$SQLITE_DROPIN" ]]; then
    echo "ERROR: SQLite db.php drop-in not found at: $SQLITE_DROPIN" >&2
    echo "Expected it to be installed during environment setup (default: $SQLITE_DROPIN_DEFAULT)." >&2
    echo "Fix: install the wp-sqlite-db drop-in into /opt/tools/wp-sqlite-db/db.php, or set WP_SQLITE_DROPIN." >&2
    exit 1
  fi
  mkdir -p "$WP_DIR/wp-content"
  cp "$SQLITE_DROPIN" "$WP_DIR/wp-content/db.php"
fi

if [[ ! -f "$WP_DIR/wp-config.php" ]]; then
  cat > "$WP_DIR/wp-config.php" <<'PHP'
<?php

define('DB_NAME', 'mailmojo');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

define('DB_DIR', __DIR__ . '/wp-content/database');
define('DB_FILE', 'mailmojo.sqlite');

define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', true);

$table_prefix = 'wp_';

if (! defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}

require_once ABSPATH . 'wp-settings.php';
PHP
fi

PLUGIN_TARGET="$WP_DIR/wp-content/plugins/mailmojo"
if [[ ! -e "$PLUGIN_TARGET" ]]; then
  ln -s "$ROOT_DIR/mailmojo" "$PLUGIN_TARGET"
fi

if ! wp core is-installed --path="$WP_DIR" >/dev/null 2>&1; then
  wp core install \
    --path="$WP_DIR" \
    --url="$URL" \
    --title="Mailmojo Local" \
    --admin_user="admin" \
    --admin_password="password" \
    --admin_email="admin@example.com" \
    --skip-email
fi

wp plugin activate mailmojo --path="$WP_DIR" >/dev/null
