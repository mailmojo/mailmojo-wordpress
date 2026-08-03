#!/usr/bin/env bash
#
# Stage the distributable plugin directory.
#
# This is the single source of truth for what ships to WordPress.org, shared by
# bin/package-plugin.sh (local zips) and .github/workflows/deploy-wordpress-org.yml
# (the actual release). Keep the exclude list here and nowhere else.
#
# Usage: bin/stage-release.sh [DEST_DIR]
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DIR="${REPO_ROOT}/mailmojo"
DEST_DIR="${1:-${REPO_ROOT}/release/mailmojo}"

if [ ! -f "${SOURCE_DIR}/mailmojo.php" ]; then
  echo "Error: ${SOURCE_DIR}/mailmojo.php not found." >&2
  exit 1
fi

if [ ! -f "${SOURCE_DIR}/vendor/autoload.php" ]; then
  echo "Error: ${SOURCE_DIR}/vendor is missing. Run:" >&2
  echo "  composer install --working-dir=mailmojo --no-dev --optimize-autoloader" >&2
  exit 1
fi

if [ ! -d "${SOURCE_DIR}/build" ]; then
  echo "Error: ${SOURCE_DIR}/build is missing. Run: npm run build --prefix mailmojo" >&2
  exit 1
fi

echo "Staging ${SOURCE_DIR} -> ${DEST_DIR}"

rm -rf "${DEST_DIR}"
mkdir -p "${DEST_DIR}"

# rsync pattern anchoring matters here. A pattern with a leading slash is
# anchored to the transfer root (the plugin directory); a pattern without one
# matches at *every* depth. Writing 'src/' instead of '/src/' silently deletes
# vendor/*/src -- i.e. every Guzzle and PSR class -- and produces a plugin that
# fatals on load. Anything meant to match only the plugin root is anchored below.
rsync -a \
  --exclude '.*' \
  --exclude 'node_modules/' \
  --exclude '/src/' \
  --exclude '/composer.lock' \
  --exclude '/package.json' \
  --exclude '/package-lock.json' \
  --exclude '/phpcs.xml' \
  --exclude 'test/' \
  --exclude 'tests/' \
  --exclude 'Test/' \
  --exclude 'Tests/' \
  --exclude 'docs/' \
  --exclude '*.md' \
  --exclude '*.sh' \
  --exclude 'Dockerfile' \
  --exclude '*.dist' \
  "${SOURCE_DIR}/" "${DEST_DIR}/"

echo "Staged $(find "${DEST_DIR}" -type f | wc -l | xargs) files."
