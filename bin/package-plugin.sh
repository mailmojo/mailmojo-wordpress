#!/usr/bin/env bash
set -euo pipefail

VERSION="$(sed -n 's/^[[:space:]]*\* Version:[[:space:]]*//p' mailmojo/mailmojo.php | head -n 1 | xargs)"
ZIP_NAME="dist/mailmojo-${VERSION}.zip"

echo "Packaging plugin v${VERSION}..."

composer install --working-dir=mailmojo --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
npm run build --prefix mailmojo

mkdir -p dist
rm -rf release
mkdir -p release/mailmojo

rsync -a \
  --exclude '.*' \
  --exclude 'node_modules/' \
  --exclude '/src/' \
  --exclude 'composer.json' \
  --exclude 'composer.lock' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  mailmojo/ release/mailmojo/

(cd release && zip -qr "$(pwd)/../${ZIP_NAME}" mailmojo)

echo "Created ${ZIP_NAME}"
