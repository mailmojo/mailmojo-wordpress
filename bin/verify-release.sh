#!/usr/bin/env bash
#
# Verify that a staged (or extracted) plugin directory is complete and loadable.
#
# The important check is autoload integrity: every path Composer's generated
# autoload files point at must exist on disk. A packaging mistake that strips
# vendor source directories leaves those references dangling, and Composer's
# autoload_files entries are `require`d unconditionally -- so the plugin fatals
# on load rather than failing quietly.
#
# Usage: bin/verify-release.sh [PLUGIN_DIR]
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="${1:-${REPO_ROOT}/release/mailmojo}"
PLUGIN_DIR="${PLUGIN_DIR%/}"
VENDOR_DIR="${PLUGIN_DIR}/vendor"

failed=0
pass() { echo "  ok   $*"; }
fail() {
  echo "  FAIL $*" >&2
  failed=1
}

if [ ! -d "${PLUGIN_DIR}" ]; then
  echo "Error: ${PLUGIN_DIR} does not exist." >&2
  exit 1
fi

echo "Verifying ${PLUGIN_DIR}"

echo "Required contents:"
for path in \
  mailmojo.php \
  readme.txt \
  uninstall.php \
  build/blocks-manifest.php \
  includes \
  assets \
  languages \
  vendor/autoload.php \
  vendor/composer/autoload_real.php \
  vendor/eliksir/mailmojo-php-sdk/MailMojo/Configuration.php \
  vendor/guzzlehttp/guzzle/src/Client.php; do
  if [ -e "${PLUGIN_DIR}/${path}" ]; then
    pass "${path}"
  else
    fail "missing ${path}"
  fi
done

echo "Excluded contents:"
for path in \
  src \
  node_modules \
  composer.lock \
  package.json \
  package-lock.json \
  phpcs.xml; do
  if [ -e "${PLUGIN_DIR}/${path}" ]; then
    fail "${path} should not be shipped"
  else
    pass "no ${path}"
  fi
done

echo "Composer autoload integrity:"
if [ ! -d "${VENDOR_DIR}/composer" ]; then
  fail "vendor/composer is missing"
else
  # Composer's generated autoload_*.php files reference every classmap target,
  # PSR-4/PSR-0 root and always-required file as $vendorDir/$baseDir + literal.
  refs="$(grep -hoE '\$(vendorDir|baseDir) \. .[^'\'']+' "${VENDOR_DIR}"/composer/autoload_*.php 2>/dev/null | sort -u || true)"

  if [ -z "${refs}" ]; then
    fail "found no autoload references to check"
  else
    checked=0
    missing=0
    while IFS= read -r ref; do
      [ -n "${ref}" ] || continue
      rel="${ref#*\'}"
      case "${ref}" in
        '$vendorDir'*) target="${VENDOR_DIR}${rel}" ;;
        '$baseDir'*) target="${PLUGIN_DIR}${rel}" ;;
        *) continue ;;
      esac
      checked=$((checked + 1))
      if [ ! -e "${target}" ]; then
        missing=$((missing + 1))
        # Report relative to the plugin dir to keep output readable.
        fail "dangling autoload reference: ${target#"${PLUGIN_DIR}"/}"
      fi
    done <<EOF
${refs}
EOF

    if [ "${missing}" -eq 0 ]; then
      pass "all ${checked} autoload references resolve"
    else
      echo "  ${missing} of ${checked} autoload references are missing" >&2
    fi
  fi
fi

echo "Runtime load test:"
if command -v php >/dev/null 2>&1; then
  if php -r '
    $dir = $argv[1];
    require $dir . "/vendor/autoload.php";
    $classes = array(
      "MailMojo\\Configuration",
      "MailMojo\\ApiException",
      "MailMojo\\Api\\AccountApi",
      "MailMojo\\Api\\ListApi",
      "MailMojo\\Model\\AccountSdkDetails",
      "GuzzleHttp\\Client",
      "GuzzleHttp\\Psr7\\Request",
      "Psr\\Http\\Message\\RequestInterface",
    );
    $bad = array();
    foreach ($classes as $class) {
      if (!class_exists($class) && !interface_exists($class)) {
        $bad[] = $class;
      }
    }
    if (!function_exists("getallheaders")) {
      $bad[] = "getallheaders()";
    }
    if ($bad) {
      fwrite(STDERR, "unresolvable: " . implode(", ", $bad) . PHP_EOL);
      exit(1);
    }
    exit(0);
  ' "${PLUGIN_DIR}"; then
    pass "autoloader loads and resolves the classes the plugin uses"
  else
    fail "autoloader could not load the classes the plugin uses"
  fi
else
  echo "  skip (php not on PATH; the autoload integrity check above still ran)"
fi

if [ "${failed}" -ne 0 ]; then
  echo "Verification FAILED for ${PLUGIN_DIR}" >&2
  exit 1
fi

echo "Verification passed for ${PLUGIN_DIR}"
