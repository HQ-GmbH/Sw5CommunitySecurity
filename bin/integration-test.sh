#!/usr/bin/env bash
#
# Integration test against a running Shopware 5 installation with this plugin
# installed and activated. Checks the security headers of real responses and
# that configuration changes and deactivation take effect.
#
# Usage: bin/integration-test.sh <shopware-root> <base-url>
#   e.g. bin/integration-test.sh /var/www/shopware http://127.0.0.1:8000

set -euo pipefail

readonly PLUGIN_NAME="Sw5CommunitySecurity"

if [[ $# -ne 2 ]]; then
    echo "Usage: $0 <shopware-root> <base-url>" >&2
    exit 2
fi

shopware_root="$1"
base_url="${2%/}"
headers_file="$(mktemp)"
raw_headers_file="$(mktemp)"
trap 'rm -f "${headers_file}" "${raw_headers_file}"' EXIT

console() {
    (cd "${shopware_root}" && php bin/console "$@" --no-interaction)
}

set_config() {
    console sw:plugin:config:set "${PLUGIN_NAME}" "$1" "$2" --shopId=1
    console sw:cache:clear
}

# Fetches a URL and stores normalised headers ("name: value", lower-case name).
fetch() {
    local url="$1" status
    status="$(curl -sS --max-time 180 -o /dev/null -D "${raw_headers_file}" -w '%{http_code}' "${url}")"
    tr -d '\r' < "${raw_headers_file}" \
        | awk -F': ' 'NF >= 2 { printf "%s: %s\n", tolower($1), substr($0, length($1) + 3) }' > "${headers_file}"
    echo "GET ${url} -> HTTP ${status}"
    if [[ "${status}" != "200" ]]; then
        echo "FAIL: expected HTTP 200" >&2
        cat "${headers_file}" >&2
        exit 1
    fi
}

expect_header() {
    local name="$1" expected="$2" actual
    actual="$(grep -i "^${name}: " "${headers_file}" | head -n 1 | cut -d' ' -f2- || true)"
    if [[ "${actual}" != "${expected}" ]]; then
        echo "FAIL: ${name} expected '${expected}', got '${actual:-<missing>}'" >&2
        echo "--- response headers ---" >&2
        cat "${headers_file}" >&2
        exit 1
    fi
    echo "  ok: ${name}: ${expected}"
}

expect_no_header() {
    local name="$1"
    if grep -qi "^${name}: " "${headers_file}"; then
        echo "FAIL: ${name} must not be sent, got '$(grep -i "^${name}: " "${headers_file}")'" >&2
        exit 1
    fi
    echo "  ok: no ${name}"
}

echo "== 1. Default configuration (frontend)"
fetch "${base_url}/"
expect_header x-content-type-options "nosniff"
expect_header x-frame-options "SAMEORIGIN"
expect_header referrer-policy "strict-origin-when-cross-origin"
expect_no_header strict-transport-security # plain HTTP: HSTS must never be sent

echo "== 2. Default configuration (widgets)"
fetch "${base_url}/widgets/index/menu"
expect_header x-content-type-options "nosniff"

echo "== 3. Changed configuration is applied"
set_config headerXFrameOptions DENY
set_config headerReferrerPolicy no-referrer
fetch "${base_url}/"
expect_header x-frame-options "DENY"
expect_header referrer-policy "no-referrer"

echo "== 4. Module switch disables all headers"
set_config securityHeadersEnabled false
fetch "${base_url}/"
expect_no_header x-content-type-options
expect_no_header x-frame-options
expect_no_header referrer-policy

echo "== 5. Deactivated plugin sends no headers"
set_config securityHeadersEnabled true
fetch "${base_url}/"
expect_header x-content-type-options "nosniff"
console sw:plugin:deactivate "${PLUGIN_NAME}"
console sw:cache:clear
fetch "${base_url}/"
expect_no_header x-content-type-options

echo "All integration checks passed."
