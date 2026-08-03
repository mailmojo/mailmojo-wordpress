#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}"

VERSION="$(sed -n 's/^[[:space:]]*\* Version:[[:space:]]*//p' mailmojo/mailmojo.php | head -n 1 | xargs)"
ZIP_NAME="dist/mailmojo-${VERSION}.zip"

echo "Packaging plugin v${VERSION}..."

composer install --working-dir=mailmojo --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
npm run build --prefix mailmojo

mkdir -p dist
rm -f "${ZIP_NAME}"
rm -rf release

# Staging and verification are shared with the WordPress.org deploy workflow so
# the zip built here matches what actually gets released.
bin/stage-release.sh release/mailmojo
bin/verify-release.sh release/mailmojo

(cd release && zip -qr "$(pwd)/../${ZIP_NAME}" mailmojo)

echo "Created ${ZIP_NAME}"

# Restore dev dependencies so local tooling (phpcs, wp-cli) still works after packaging
composer install --working-dir=mailmojo --prefer-dist --no-interaction --no-progress
