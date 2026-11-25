#!/usr/bin/env bash
#
# This file is part of Sw5CommunitySecurity. (c) HQ GmbH, licensed under the
# GNU General Public License v3.0 (GPL-3.0-only), see LICENSE.
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
# All template markers used are harmless; only the JavaScript escaping check
# renders a plain text marker.
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
upload_dir="$(mktemp -d)"
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
    rm -rf "${upload_dir}"
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

# Without a shop option Shopware uses the default shop. "--shopId" is not
# used: Shopware 5.6.10 reads the "--shop" option instead
# (engine/Shopware/Commands/PluginConfigSetCommand.php, v5.6.10).
set_config() {
    console sw:plugin:config:set "${PLUGIN_NAME}" "$1" "$2"
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

echo "== 0. Scan of the fresh installation finds nothing"
console sw5cs:template-guard:scan
echo "  ok: no findings in Shopware's own templates and settings"

echo "== 1. Default configuration (frontend)"
fetch "${base_url}/"
expect_header x-content-type-options "nosniff"
expect_header x-frame-options "SAMEORIGIN"
expect_header referrer-policy "strict-origin-when-cross-origin"
expect_header permissions-policy "camera=(), microphone=(), usb=()"
expect_no_header strict-transport-security # plain HTTP: HSTS must never be sent
expect_no_header content-security-policy # off by default
expect_no_header content-security-policy-report-only

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

echo "== 3b. Content-Security-Policy and Permissions-Policy"
default_csp="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; frame-src 'self' https://www.youtube-nocookie.com; object-src 'none'; base-uri 'self'; frame-ancestors 'self'"
set_config headerCspMode report-only
set_config headerCspReportUri "https://csp-report.example.org/report"
apply_changes
fetch "${base_url}/"
expect_header content-security-policy-report-only "${default_csp}; report-uri https://csp-report.example.org/report"
expect_no_header content-security-policy
set_config headerCspMode enforce
set_config headerCspPolicy "default-src 'self'; img-src 'self' data:"
set_config headerCspReportUri ""
set_config headerPermissionsPolicy "camera=(), geolocation=(self)"
apply_changes
fetch "${base_url}/"
expect_header content-security-policy "default-src 'self'; img-src 'self' data:"
expect_no_header content-security-policy-report-only
expect_header permissions-policy "camera=(), geolocation=(self)"
set_config headerCspPolicy "default-src 'self', script-src *"
set_config headerPermissionsPolicy "camera"
apply_changes
fetch "${base_url}/"
expect_no_header content-security-policy # invalid policy is not sent
expect_no_header permissions-policy
set_config headerCspMode off
set_config headerCspPolicy "${default_csp}"
set_config headerPermissionsPolicy "camera=(), microphone=(), usb=()"
apply_changes

echo "== 4. Module switch disables all headers"
set_config securityHeadersEnabled false
apply_changes
fetch "${base_url}/"
expect_no_header x-content-type-options
expect_no_header x-frame-options
expect_no_header referrer-policy
expect_no_header permissions-policy

echo "== 4b. Saving the configuration in the backend takes effect without clearing caches"
set_config securityHeadersEnabled true
set_config headerXFrameOptions SAMEORIGIN
apply_changes
fetch "${base_url}/"
expect_header x-frame-options "SAMEORIGIN"
element_id="$(php "${script_dir}/../Tests/Integration/config-element-id.php" "${shopware_root}" headerXFrameOptions)"
backend_login
backend_post Config/saveForm --data-urlencode "id=0" --data-urlencode "name=${PLUGIN_NAME}" \
    --data-urlencode "elements[0][id]=${element_id}" --data-urlencode "elements[0][name]=headerXFrameOptions" \
    --data-urlencode "elements[0][type]=select" --data-urlencode "elements[0][scope]=1" \
    --data-urlencode "elements[0][value]=SAMEORIGIN" \
    --data-urlencode "elements[0][values][0][shopId]=1" --data-urlencode "elements[0][values][0][value]=DENY"
expect_backend_success "plugin configuration saved in the backend"
fetch "${base_url}/"
expect_header x-frame-options "DENY"
set_config headerXFrameOptions SAMEORIGIN
apply_changes

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
backend_post NewsletterManager/createNewsletter --data-urlencode 'subject={fetch file="sw5cs-integration-marker.txt"}' \
    --data-urlencode "senderMail=sw5cs-marker@example.org" --data-urlencode "senderName=sw5cs marker"
expect_guard_rejection "saving a newsletter with {fetch} in the subject is rejected"
backend_post NewsletterManager/createNewsletter --data-urlencode "subject=sw5cs marker" \
    --data-urlencode 'containers[0][text][0][content]={extends file="sw5cs-integration-marker.tpl"}'
expect_guard_rejection "saving a newsletter with {extends} in a text container is rejected"
backend_post Config/saveForm --data-urlencode "elements[0][id]=0" --data-urlencode "elements[0][name]=routerarticletemplate" \
    --data-urlencode "elements[0][values][0][shopId]=1" \
    --data-urlencode 'elements[0][values][0][value]={fetch file="sw5cs-integration-marker.txt"}'
expect_guard_rejection "saving an SEO template with {fetch} is rejected"
backend_post Config/saveForm --data-urlencode "elements[0][id]=0" --data-urlencode "elements[0][name]=emailheaderhtml" \
    --data-urlencode "elements[0][values][0][shopId]=1" \
    --data-urlencode 'elements[0][values][0][value]={include file=$sw5csIntegrationMarker}'
expect_guard_rejection "saving the mail header with {include} from a variable is rejected"
backend_post Config/saveValues --data-urlencode "_repositoryClass=document" --data-urlencode "id=1" \
    --data-urlencode "elements[0][id]=1" --data-urlencode 'elements[0][value]={math equation=$sw5csIntegrationMarker}'
expect_guard_rejection "saving a document box with {math} from a variable is rejected"
backend_post Config/saveValues --data-urlencode "_repositoryClass=country" --data-urlencode "id=2" \
    --data-urlencode 'notice={extends file="sw5cs-integration-marker.tpl"}'
expect_guard_rejection "saving a country notice with {extends} is rejected"
backend_post Payment/updatePayments --data-urlencode "id=4" \
    --data-urlencode 'additionalDescription={fetch file="sw5cs-integration-marker.txt"}'
expect_guard_rejection "saving a payment description with {fetch} is rejected"
backend_post Translation/saveTranslation --data-urlencode "type=documents" --data-urlencode "key=1" \
    --data-urlencode "language=2" --data-urlencode 'data[1][Body_Value]={fetch file="sw5cs-integration-marker.txt"}'
expect_guard_rejection "saving a document box translation with {fetch} is rejected"
backend_post Mail/verifySmarty --data-urlencode "id=${SW_TEST_MAIL_ID}" --data-urlencode 'value={Sw5csIntegrationMarker::VALUE}'
expect_guard_rejection "Smarty check with static class access is rejected"
backend_post Snippet/createSnippet --data-urlencode "namespace=sw5cs/integration" --data-urlencode "name=marker" \
    --data-urlencode "shopId=1" --data-urlencode "localeId=1" --data-urlencode 'value={extends file="sw5cs-integration-marker.tpl"}'
expect_guard_rejection "saving a snippet with {extends} is rejected"
printf 'namespace;name;de_DE\nsw5cs/integration;marker;{fetch file="sw5cs-integration-marker.txt"}\n' > "${upload_dir}/snippets.csv"
backend_post Snippet/importSnippet -F "file=@${upload_dir}/snippets.csv"
expect_guard_rejection "importing snippets with {fetch} is rejected"
backend_post Emotion/save --data-urlencode "name=sw5cs integration marker" \
    --data-urlencode 'elements[0][data][0][value]={Sw5csIntegrationMarker::VALUE}'
expect_guard_rejection "saving a shopping world with static class access is rejected"
backend_post Translation/saveTranslation --data-urlencode "type=emotionElement" --data-urlencode "key=1" \
    --data-urlencode "language=2" --data-urlencode 'data[smarty]={fetch file="sw5cs-integration-marker.txt"}'
expect_guard_rejection "saving a shopping world element translation with {fetch} is rejected"

# The CI backend user has the locale de_DE: messages come from the plugin's snippets.
backend_post Mail/verifySmarty --data-urlencode "id=${SW_TEST_MAIL_ID}" --data-urlencode 'value={fetch file="sw5cs-integration-marker.txt"}'
expect_guard_rejection "rejection message"
if ! grep -q 'Smarty-Konstrukte' "${backend_response}" || grep -q 'The template contains' "${backend_response}"; then
    echo "FAIL: rejection message does not come from the German snippet: $(head -c 500 "${backend_response}")" >&2
    exit 1
fi
echo "  ok: rejection message from the German snippet"

echo "== 5b. Upload hardening (backend)"
printf 'sw5cs integration marker\n' > "${upload_dir}/sw5cs-marker.phar"
printf 'sw5cs integration marker\n' > "${upload_dir}/sw5cs-marker.phar.jpg"
printf 'GIF89a sw5cs integration marker __HALT_COMPILER();\n' > "${upload_dir}/sw5cs-marker.gif"
printf '%%PDF-1.4 sw5cs harmless upload\n' > "${upload_dir}/sw5cs-harmless.pdf"
backend_post MediaManager/upload -F "fileId=@${upload_dir}/sw5cs-marker.phar"
expect_guard_rejection "media upload of a .phar file is rejected"
backend_post MediaManager/upload -F "fileId=@${upload_dir}/sw5cs-marker.phar.jpg"
expect_guard_rejection "media upload with double extension .phar.jpg is rejected"
backend_post MediaManager/upload -F "fileId=@${upload_dir}/sw5cs-marker.gif"
expect_guard_rejection "media upload containing a PHAR marker is rejected"
backend_post Article/uploadEsdFile -F "fileId=@${upload_dir}/sw5cs-marker.phar"
expect_guard_rejection "ESD upload of a .phar file is rejected"
backend_post MediaManager/upload -F "fileId=@${upload_dir}/sw5cs-harmless.pdf"
expect_backend_success "harmless media upload is accepted"

echo "== 5c. Rejections are logged without content"
plugin_logs=("${shopware_root}"/var/log/sw5_community_security_*.log)
if [[ ! -f "${plugin_logs[0]}" ]]; then
    echo "FAIL: no plugin log file in var/log" >&2
    ls -la "${shopware_root}/var/log" >&2
    exit 1
fi
if ! grep -q 'Template guard rejected a template' "${plugin_logs[@]}" || ! grep -q 'Upload guard rejected a file' "${plugin_logs[@]}"; then
    echo "FAIL: rejections are not logged" >&2
    exit 1
fi
if grep -qiE 'sw5cs-integration-marker|sw5cs-marker|Sw5csIntegrationMarker|ci-admin|127\.0\.0\.1' "${plugin_logs[@]}"; then
    echo "FAIL: plugin log contains template content, file names, user names or IP addresses" >&2
    exit 1
fi
echo "  ok: rejections logged with rules and extensions only"

echo "== 5d. JavaScript escaping (compiled templates)"
php "${script_dir}/../Tests/Integration/javascript-escape.php" "${shopware_root}" hardened
set_config javaScriptEscapeEnabled false
apply_changes
php "${script_dir}/../Tests/Integration/javascript-escape.php" "${shopware_root}" legacy
set_config javaScriptEscapeEnabled true
apply_changes
php "${script_dir}/../Tests/Integration/javascript-escape.php" "${shopware_root}" hardened

echo "== 5e. Security check (console and backend)"
php "${script_dir}/../Tests/Integration/acl-resource.php" "${shopware_root}" present
check_output="$(mktemp)"
set +e
console sw5cs:security-check > "${check_output}" 2>&1
check_exit=$?
set -e
check_rows="$(grep -cE '^ +(OK|INFO|WARNING|ERROR) +' "${check_output}" || true)"
if [[ "${check_rows}" != "14" ]]; then
    echo "FAIL: security check: expected 14 result rows, got ${check_rows}" >&2
    cat "${check_output}" >&2
    exit 1
fi
expected_exit=0
if grep -qE '^ +ERROR +' "${check_output}"; then
    expected_exit=1
fi
if [[ "${check_exit}" != "${expected_exit}" ]]; then
    echo "FAIL: security check: exit code ${check_exit}, expected ${expected_exit}" >&2
    cat "${check_output}" >&2
    exit 1
fi
rm -f "${check_output}"
echo "  ok: console: 14 checks, exit code ${check_exit}"

check_page="$(mktemp)"
# fetch_check_page [curl args...]: GET the backend page with the current session
fetch_check_page() {
    check_status="$(curl -sS --max-time 120 -c "${cookie_jar}" -b "${cookie_jar}" -o "${check_page}" -w '%{http_code}' "$@" "${base_url}/backend/Sw5csSecurityCheck")"
}
: > "${cookie_jar}"
fetch_check_page
if grep -q 'data-check=' "${check_page}"; then
    echo "FAIL: security check page is shown without backend login" >&2
    exit 1
fi
echo "  ok: no results without backend login (HTTP ${check_status})"
backend_login
fetch_check_page
if [[ "${check_status}" != "200" || "$(grep -c 'data-check=' "${check_page}")" != "14" ]]; then
    echo "FAIL: security check page: HTTP ${check_status}, expected 14 rows" >&2
    head -c 2000 "${check_page}" >&2
    exit 1
fi
grep -q '<tr class="ok" data-check="trusted_proxies">' "${check_page}" || { echo "FAIL: trusted_proxies not ok" >&2; exit 1; }
grep -q '<tr class="ok" data-check="protection_files">' "${check_page}" || { echo "FAIL: protection files of the fresh installation not ok" >&2; exit 1; }
if grep -q 'data-action="restore-protection-files"' "${check_page}"; then
    echo "FAIL: restore button shown although no protection file is missing" >&2
    exit 1
fi
echo "  ok: backend page with 14 checks"
fetch_check_page -H 'X-Forwarded-Host: sw5cs-integration.invalid'
grep -q '<tr class="warning" data-check="trusted_proxies">' "${check_page}" || { echo "FAIL: forwarded header from untrusted source not reported" >&2; exit 1; }
echo "  ok: forwarding header from an untrusted address is reported"

echo "== 5f. Protection files are restored"
protection_files=(var/log/.htaccess vendor/.htaccess engine/Library/.htaccess)
remove_protection_files() {
    for file in "${protection_files[@]}"; do
        rm -f "${shopware_root}/${file}"
    done
}
expect_protection_files() {
    local label="$1" file
    for file in "${protection_files[@]}"; do
        if [[ ! -f "${shopware_root}/${file}" ]] || ! grep -q 'Require all denied' "${shopware_root}/${file}"; then
            echo "FAIL: ${label}: ${file} missing or without deny rule" >&2
            exit 1
        fi
    done
    echo "  ok: ${label}"
}
expect_no_protection_files() {
    local label="$1" file
    for file in "${protection_files[@]}"; do
        if [[ -e "${shopware_root}/${file}" ]]; then
            echo "FAIL: ${label}: ${file} was created" >&2
            exit 1
        fi
    done
    echo "  ok: ${label}"
}
# Keeps the shop's own files to put them back at the end.
protection_backup="$(mktemp -d)"
for file in "${protection_files[@]}"; do
    mkdir -p "${protection_backup}/$(dirname "${file}")"
    cp "${shopware_root}/${file}" "${protection_backup}/${file}"
done

remove_protection_files
fetch_check_page
grep -q '<tr class="warning" data-check="protection_files">' "${check_page}" || { echo "FAIL: missing protection files not reported" >&2; exit 1; }
grep -q 'data-action="restore-protection-files"' "${check_page}" || { echo "FAIL: restore button missing" >&2; exit 1; }
echo "  ok: missing files reported, restore button shown"

console sw5cs:protection-files:restore
expect_protection_files "restored by the console command"
# PHP's built-in web server ignores .htaccess, so only the rules are checked.
grep -q '<FilesMatch "\\.php\$">' "${shopware_root}/engine/Library/.htaccess" || { echo "FAIL: engine/Library must only deny PHP files" >&2; exit 1; }
echo "  ok: engine/Library denies PHP files only"

remove_protection_files
backend_post Cache/clearCache --data 'cache[frontend]=on'
expect_backend_success "backend: clear frontend caches"
expect_protection_files "restored automatically when the HTTP cache is cleared"

set_config protectionFilesAutoRestore false
apply_changes
backend_login
remove_protection_files
backend_post Cache/clearCache --data 'cache[frontend]=on'
expect_backend_success "backend: clear frontend caches"
expect_no_protection_files "not restored automatically when switched off"

restore_url="${base_url}/backend/Sw5csSecurityCheck/restoreProtectionFiles"
curl -sS --max-time 60 -c "${cookie_jar}" -b "${cookie_jar}" -o /dev/null "${restore_url}"
curl -sS --max-time 60 -c "${cookie_jar}" -b "${cookie_jar}" -o /dev/null --data '__csrf_token=invalid' "${restore_url}"
expect_no_protection_files "backend: GET and an invalid CSRF token change nothing"

fetch_check_page
page_token="$(sed -n 's/.*name="__csrf_token" value="\([^"]*\)".*/\1/p' "${check_page}" | head -n 1)"
if [[ -z "${page_token}" ]]; then
    echo "FAIL: restore form without CSRF token" >&2
    exit 1
fi
restore_location="$(curl -sS --max-time 60 -c "${cookie_jar}" -b "${cookie_jar}" -o /dev/null -D - \
    --data-urlencode "__csrf_token=${page_token}" "${restore_url}" | tr -d '\r' | awk -F': ' 'tolower($1) == "location" { print $2 }')"
case "${restore_location}" in
    *result=done*) ;;
    *) echo "FAIL: restore did not redirect with result=done (Location: ${restore_location:-<none>})" >&2; exit 1 ;;
esac
expect_protection_files "restored from the backend page"
fetch_check_page
grep -q '<tr class="ok" data-check="protection_files">' "${check_page}" || { echo "FAIL: protection files still reported as missing" >&2; exit 1; }
if ! grep -q 'Missing protection files were restored' "${plugin_logs[@]}"; then
    echo "FAIL: restoring is not logged" >&2
    exit 1
fi
echo "  ok: restoring is logged"

set_config protectionFilesAutoRestore true
apply_changes
for file in "${protection_files[@]}"; do
    cp "${protection_backup}/${file}" "${shopware_root}/${file}"
done
rm -rf "${protection_backup}" "${check_page}"

echo "== 6. Template guard (Doctrine fallback)"
php "${script_dir}/../Tests/Integration/doctrine-template-guard.php" "${shopware_root}"

echo "== 6b. Scan of stored templates"
console sw5cs:template-guard:scan
echo "  ok: rejected templates were not stored"
php "${script_dir}/../Tests/Integration/scan-fixture.php" "${shopware_root}" add
scan_output="$(mktemp)"
if console sw5cs:template-guard:scan > "${scan_output}" 2>&1; then
    echo "FAIL: scan did not report the stored marker" >&2
    cat "${scan_output}" >&2
    exit 1
fi
if ! grep -q 'include-file' "${scan_output}" || ! grep -q 'Body_Value' "${scan_output}" \
    || ! grep -q 'static-class' "${scan_output}" || ! grep -q 'sw5cs/scan-fixture' "${scan_output}"; then
    echo "FAIL: scan output does not name field and rule" >&2
    cat "${scan_output}" >&2
    exit 1
fi
if grep -qi 'sw5csScanMarker' "${scan_output}"; then
    echo "FAIL: scan output contains template content" >&2
    exit 1
fi
echo "  ok: stored marker reported with field and rule, without content"
console sw5cs:template-guard:scan --relaxed > "${scan_output}" 2>&1 || true
if grep -q 'include-file' "${scan_output}" || ! grep -q 'static-class' "${scan_output}"; then
    echo "FAIL: --relaxed must skip only the {include}/{math} rules" >&2
    cat "${scan_output}" >&2
    exit 1
fi
echo "  ok: --relaxed skips the {include} rule but keeps the static class rule"
php "${script_dir}/../Tests/Integration/scan-fixture.php" "${shopware_root}" remove
rm -f "${scan_output}"

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

echo "== 8. Deactivated plugin sends no headers and no longer hardens templates"
fetch "${base_url}/"
expect_header x-content-type-options "nosniff"
console sw:plugin:deactivate "${PLUGIN_NAME}"
apply_changes
fetch "${base_url}/"
expect_no_header x-content-type-options
php "${script_dir}/../Tests/Integration/javascript-escape.php" "${shopware_root}" legacy

echo "== 9. Uninstall"
console sw:plugin:uninstall "${PLUGIN_NAME}"
echo "  ok: plugin uninstalled"
php "${script_dir}/../Tests/Integration/acl-resource.php" "${shopware_root}" absent

echo "All integration checks passed."
