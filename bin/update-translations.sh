#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="mailmojo"
LANGUAGES_DIR="${PLUGIN_DIR}/languages"
TEXT_DOMAIN="mailmojo"
WP_BOOT="./mailmojo/vendor/wp-cli/wp-cli/php/boot-fs.php"
wp() { php -d error_reporting="E_ALL & ~E_DEPRECATED" "${WP_BOOT}" "$@"; }

echo "Updating translation files..."

# Generate/update .pot from all PHP and JS source files
wp i18n make-pot "${PLUGIN_DIR}" "${LANGUAGES_DIR}/${TEXT_DOMAIN}.pot" \
  --domain="${TEXT_DOMAIN}" \
  --exclude="vendor,node_modules,build" \
  --headers='{"Report-Msgid-Bugs-To":"support@mailmojo.no","Language-Team":"Mailmojo AS <support@mailmojo.no>"}'

echo "Updated ${LANGUAGES_DIR}/${TEXT_DOMAIN}.pot"

# Merge new strings into each existing .po file and recompile
for po_file in "${LANGUAGES_DIR}"/*.po; do
  locale="$(basename "${po_file}" .po | sed "s/^${TEXT_DOMAIN}-//")"
  mo_file="${LANGUAGES_DIR}/${TEXT_DOMAIN}-${locale}.mo"

  msgmerge --update --quiet --backup=none "${po_file}" "${LANGUAGES_DIR}/${TEXT_DOMAIN}.pot"
  msgfmt --check -o "${mo_file}" "${po_file}"

  echo "Updated ${po_file} and ${mo_file}"
done

# Generate JSON files for JS translations (one per .po file, per JS file)
wp i18n make-json "${LANGUAGES_DIR}" \
  && echo "Updated JS translation JSON files"

echo "Done."
