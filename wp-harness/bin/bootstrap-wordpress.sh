#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
HARNESS_DIR="$ROOT_DIR/wp-harness"
WP_DIR="$HARNESS_DIR/wordpress"
RUNTIME_DIR="$HARNESS_DIR/runtime"

PORT="${WP_PORT:-8888}"
HOST="${WP_HOST:-localhost}"
URL="${WP_URL:-http://${HOST}:${PORT}}"

WP_CLI_BIN="${WP_CLI_BIN:-}"
PHP_BIN="${PHP_BIN:-php}"
WP_CLI_PHP_ARGS="${WP_CLI_PHP_ARGS:--d memory_limit=512M}"
WP_CLI_PHAR="$RUNTIME_DIR/wp-cli.phar"
WP_CLI_URL="https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar"

if [[ -n "$WP_CLI_BIN" ]]; then
  WP="$WP_CLI_BIN"
elif command -v wp >/dev/null 2>&1; then
  WP="wp"
elif [[ -f "$WP_CLI_PHAR" ]]; then
  WP="$PHP_BIN $WP_CLI_PHP_ARGS $WP_CLI_PHAR"
else
  if command -v curl >/dev/null 2>&1; then
    curl -sSL "$WP_CLI_URL" -o "$WP_CLI_PHAR"
  elif command -v wget >/dev/null 2>&1; then
    wget -qO "$WP_CLI_PHAR" "$WP_CLI_URL"
  else
    echo "wp-cli is required but was not found in PATH." >&2
    echo "Install wp-cli, or set WP_CLI_BIN, or provide curl/wget to auto-download." >&2
    exit 1
  fi
  chmod +x "$WP_CLI_PHAR"
  WP="$PHP_BIN $WP_CLI_PHP_ARGS $WP_CLI_PHAR"
fi

mkdir -p "$WP_DIR" "$RUNTIME_DIR"

if [[ ! -f "$WP_DIR/wp-load.php" ]]; then
  $WP core download --path="$WP_DIR" --quiet
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

if ! $WP core is-installed --path="$WP_DIR" >/dev/null 2>&1; then
  $WP core install \
    --path="$WP_DIR" \
    --url="$URL" \
    --title="Mailmojo Local" \
    --admin_user="admin" \
    --admin_password="password" \
    --admin_email="admin@example.com" \
    --skip-email
fi

$WP plugin activate mailmojo --path="$WP_DIR" >/dev/null
