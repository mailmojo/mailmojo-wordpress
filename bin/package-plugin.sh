#!/usr/bin/env bash
set -euo pipefail

VERSION="$(sed -n 's/^[[:space:]]*\* Version:[[:space:]]*//p' mailmojo/mailmojo.php | head -n 1 | xargs)"
ZIP_NAME="dist/mailmojo-${VERSION}.zip"

echo "Packaging plugin v${VERSION}..."

composer install --working-dir=mailmojo --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
npm run build --prefix mailmojo

mkdir -p dist
rm -f "${ZIP_NAME}"
rm -rf release
mkdir -p release/mailmojo

rsync -a \
  --exclude '.*' \
  --exclude 'node_modules/' \
  --exclude '/src/' \
  --exclude 'composer.lock' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude 'phpcs.xml' \
  --exclude 'test/' \
  --exclude 'tests/' \
  --exclude 'Test/' \
  --exclude 'Tests/' \
  --exclude 'docs/' \
  --exclude '*.md' \
  --exclude '*.sh' \
  --exclude 'Dockerfile' \
  --exclude '*.dist' \
  mailmojo/ release/mailmojo/

(cd release && zip -qr "$(pwd)/../${ZIP_NAME}" mailmojo)

echo "Created ${ZIP_NAME}"

# Restore dev dependencies so local tooling (phpcs, wp-cli) still works after packaging
composer install --working-dir=mailmojo --prefer-dist --no-interaction --no-progress
