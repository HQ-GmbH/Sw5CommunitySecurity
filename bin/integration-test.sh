#!/usr/bin/env bash
#
# Integration test against a Shopware 5 installation with this plugin
# installed and activated. Runs Shopware in PHP's built-in web server, checks
# the security headers of real responses and that configuration changes and
# deactivation take effect.
#
# The web server is restarted after every change made via bin/console: if
# APCu is available, Shopware caches the plugin configuration in the web
# server's own memory, which a CLI "sw:cache:clear" cannot reach.
#
# The template guard checks log in to the backend and need a backend user
# and the id of an existing mail template, passed as environment variables:
#   SW_ADMIN_USER, SW_ADMIN_PASSWORD, SW_TEST_MAIL_ID
# All template markers used are harmless; nothing is ever rendered.
#
# Usage: bin/integration-test.sh <shopware-root> <port> [server-log-file]
#   e.g. bin/integration-test.sh /var/www/shopware 8000

set -euo pipefail

readonly PLUGIN_NAME="Sw5CommunitySecurity"

if [[ $# -lt 2 || $# -gt 3 ]]; then
    echo "Usage: $0 <shopware-root> <port> [server-log-file]" >&2
    exit 2
fi

: "${SW_ADMIN_USER:?SW_ADMIN_USER must be set}"
: "${SW_ADMIN_PASSWORD:?SW_ADMIN_PASSWORD must be set}"
: "${SW_TEST_MAIL_ID:?SW_TEST_MAIL_ID must be set}"

script_dir="$(cd "$(dirname "$0")" && pwd)"
shopware_root="$1"
port="$2"
server_log="${3:-/dev/null}"
base_url="http://127.0.0.1:${port}"
headers_file="$(mktemp)"
raw_headers_file="$(mktemp)"
cookie_jar="$(mktemp)"
backend_response="$(mktemp)"
csrf_token=""
server_pid=""

stop_server() {
    if [[ -n "${server_pid}" ]]; then
        kill "${server_pid}" 2>/dev/null || true
        wait "${server_pid}" 2>/dev/null || true
        server_pid=""
    fi
}

cleanup() {
    stop_server
    rm -f "${headers_file}" "${raw_headers_file}" "${cookie_jar}" "${backend_response}"
}
trap cleanup EXIT

start_server() {
    stop_server
    (cd "${shopware_root}" && exec php -S "127.0.0.1:${port}" shopware.php) >> "${server_log}" 2>&1 &
    server_pid=$!
    for _ in $(seq 1 30); do
        if curl -s -o /dev/null "${base_url}/"; then
            return 0
        fi
        sleep 1
    done
    echo "FAIL: web server did not start on port ${port}" >&2
    exit 1
}

console() {
    (cd "${shopware_root}" && php bin/console "$@" --no-interaction)
}

set_config() {
    console sw:plugin:config:set "${PLUGIN_NAME}" "$1" "$2" --shopId=1
}

# Clears Shopware's caches and restarts the web server so no process memory
# (APCu) keeps stale configuration.
apply_changes() {
    console sw:cache:clear
    start_server
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

backend_login() {
    : > "${cookie_jar}"
    curl -sS --max-time 60 -c "${cookie_jar}" -b "${cookie_jar}" -o "${backend_response}" \
        --data-urlencode "username=${SW_ADMIN_USER}" \
        --data-urlencode "password=${SW_ADMIN_PASSWORD}" \
        "${base_url}/backend/Login/login"
    if ! grep -q '"success":true' "${backend_response}"; then
        echo "FAIL: backend login failed" >&2
        exit 1
    fi
    csrf_token="$(curl -sS --max-time 60 -c "${cookie_jar}" -b "${cookie_jar}" -o /dev/null -D - \
        "${base_url}/backend/CSRFToken/generate" | tr -d '\r' | awk -F': ' 'tolower($1) == "x-csrf-token" { print $2 }')"
    if [[ -z "${csrf_token}" ]]; then
        echo "FAIL: no CSRF token received" >&2
        exit 1
    fi
    echo "  ok: backend login"
}

# backend_post <path> <curl args...>: POST to the backend, response body in ${backend_response}
backend_post() {
    local path="$1"
    shift
    curl -sS --max-time 120 -c "${cookie_jar}" -b "${cookie_jar}" -o "${backend_response}" \
        -H "X-CSRF-Token: ${csrf_token}" -H "X-Requested-With: XMLHttpRequest" \
        "$@" "${base_url}/backend/${path}"
}

expect_backend_success() {
    local label="$1"
    if ! grep -q '"success":true' "${backend_response}"; then
        echo "FAIL: ${label}: expected success, got: $(head -c 500 "${backend_response}")" >&2
        exit 1
    fi
    echo "  ok: ${label}"
}

expect_guard_rejection() {
    local label="$1"
    if ! grep -q '"success":false' "${backend_response}" || ! grep -q 'Community Security Guard' "${backend_response}"; then
        echo "FAIL: ${label}: expected rejection by the template guard, got: $(head -c 500 "${backend_response}")" >&2
        exit 1
    fi
    echo "  ok: ${label}"
}

start_server

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
apply_changes
fetch "${base_url}/"
expect_header x-frame-options "DENY"
expect_header referrer-policy "no-referrer"

echo "== 4. Module switch disables all headers"
set_config securityHeadersEnabled false
apply_changes
fetch "${base_url}/"
expect_no_header x-content-type-options
expect_no_header x-frame-options
expect_no_header referrer-policy

echo "== 5. Template guard (backend)"
set_config securityHeadersEnabled true
apply_changes
backend_login
backend_post Mail/verifySmarty --data-urlencode "id=${SW_TEST_MAIL_ID}" --data-urlencode "value=Hallo"
expect_backend_success "safe template passes the Smarty check"
backend_post Mail/verifySmarty --data-urlencode "id=${SW_TEST_MAIL_ID}" \
    --data-urlencode 'value={fetch file="sw5cs-integration-marker.txt"}'
expect_guard_rejection "Smarty check with {fetch} is rejected"
backend_post Mail/updateMail --data-urlencode "id=${SW_TEST_MAIL_ID}" \
    --data-urlencode 'content={extends file="sw5cs-integration-marker.tpl"}'
expect_guard_rejection "saving a mail template with {extends} is rejected"
backend_post Translation/saveTranslation --data-urlencode "type=config_mails" \
    --data-urlencode "key=${SW_TEST_MAIL_ID}" --data-urlencode "language=1" \
    --data-urlencode 'data[content]={function name="sw5cs marker"}x{/function}'
expect_guard_rejection "saving a mail translation with an invalid {function} name is rejected"
backend_post ProductFeed/saveFeed --data-urlencode "name=sw5cs integration marker" \
    --data-urlencode "header=id" --data-urlencode 'body={fetch file="sw5cs-integration-marker.txt"}'
expect_guard_rejection "saving a product export with {fetch} is rejected"

echo "== 6. Template guard (Doctrine fallback)"
php "${script_dir}/../Tests/Integration/doctrine-template-guard.php" "${shopware_root}"

echo "== 7. Template guard can be switched off"
set_config templateGuardEnabled false
apply_changes
backend_login
backend_post Mail/verifySmarty --data-urlencode "id=${SW_TEST_MAIL_ID}" \
    --data-urlencode 'value={fetch file="sw5cs-integration-marker.txt"}'
if grep -q 'Community Security Guard' "${backend_response}"; then
    echo "FAIL: template guard still active although disabled" >&2
    exit 1
fi
echo "  ok: disabled template guard does not interfere"
set_config templateGuardEnabled true
apply_changes

echo "== 8. Deactivated plugin sends no headers"
fetch "${base_url}/"
expect_header x-content-type-options "nosniff"
console sw:plugin:deactivate "${PLUGIN_NAME}"
apply_changes
fetch "${base_url}/"
expect_no_header x-content-type-options

echo "All integration checks passed."
