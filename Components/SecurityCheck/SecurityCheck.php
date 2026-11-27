<?php

declare(strict_types=1);

/*
 * This file is part of Sw5CommunitySecurity.
 *
 * (c) HQ GmbH <https://www.hqgmbh.de>
 *
 * Licensed under the GNU General Public License v3.0.
 * See the LICENSE file distributed with this source code.
 */

namespace Sw5CommunitySecurity\Components\SecurityCheck;

use DateTimeImmutable;
use Sw5CommunitySecurity\Components\ApiAccess\ApiAccessPolicy;
use Sw5CommunitySecurity\Components\Config\PluginConfig;
use Sw5CommunitySecurity\Components\ProtectionFiles\ProtectionFiles;

/**
 * Evaluates an environment snapshot. Read-only: nothing is changed.
 *
 * Deliberately free of Shopware dependencies to keep it unit-testable.
 */
final class SecurityCheck
{
    /**
     * Latest Shopware 5 release.
     */
    public const LATEST_SHOPWARE_VERSION = '5.7.20';

    /**
     * End of security support per PHP branch, from
     * https://www.php.net/supported-versions.php and https://www.php.net/eol.php
     * (retrieved 2026-11-24).
     */
    public const PHP_SECURITY_SUPPORT_UNTIL = [
        '7.4' => '2022-11-28',
        '8.0' => '2023-11-26',
        '8.1' => '2025-12-31',
        '8.2' => '2026-12-31',
        '8.3' => '2027-12-31',
        '8.4' => '2028-12-31',
        '8.5' => '2029-12-31',
        '8.6' => '2030-12-31',
    ];

    /**
     * Warn this many days before the end of security support.
     */
    public const PHP_WARNING_DAYS = 90;

    /**
     * English titles; snippet key "check_<id>".
     */
    public const TITLES = [
        'shopware_version' => 'Shopware version',
        'php_version' => 'PHP version',
        'upload_progress' => 'PHP session upload progress',
        'trusted_proxies' => 'Trusted proxies',
        'shop_host' => 'Shop hosts',
        'template_sandbox' => 'Template sandbox',
        'protection_files' => 'Protection files (.htaccess)',
        'installer' => 'Installer directory',
        'password_reset_captcha' => 'Captcha for “Forgot password”',
        'api_access' => 'REST API access',
        'smarty_output_cache' => 'Smarty output cache',
        'input_filter' => 'Shopware input filter',
        'module_security_headers' => 'Module: security headers',
        'module_csp' => 'Module: Content Security Policy',
        'module_template_guard' => 'Module: template guard',
        'module_content_guard' => 'Module: content check',
        'module_request_guard' => 'Module: request hardening',
        'module_upload_guard' => 'Module: upload hardening',
        'module_javascript_escape' => 'Module: JavaScript escaping',
        'module_backend_two_factor' => 'Module: two-factor login',
    ];

    /**
     * @return list<CheckResult>
     */
    public function evaluate(Environment $environment, PluginConfig $config, DateTimeImmutable $today): array
    {
        return [
            $this->shopwareVersion($environment->shopwareVersion),
            $this->phpVersion($environment->phpVersion, $today),
            $this->uploadProgress($environment->uploadProgressEnabled),
            $this->trustedProxies($environment),
            $this->shopHost($environment->shopsWithoutHost),
            $this->templateSandbox($environment->templateSandboxActive),
            $this->protectionFiles($environment),
            $this->installer($environment),
            $this->passwordResetCaptcha($environment),
            $this->apiAccess($environment, $config),
            $this->smartyOutputCache($environment),
            $this->inputFilter($environment),
            ...$this->modules($config),
            $this->backendTwoFactorModule($environment, $config),
        ];
    }

    /**
     * Switching the module off lifts the code check for every backend user,
     * including administrators. Intended as emergency switch, so it is a
     * warning as long as users have set up a second factor.
     */
    private function backendTwoFactorModule(Environment $environment, PluginConfig $config): CheckResult
    {
        $id = 'module_backend_two_factor';
        $users = $environment->twoFactorEnrolledUsers;

        if ($config->backendTwoFactorEnabled) {
            return $users === null
                ? new CheckResult($id, CheckResult::STATUS_OK, 'module_on', 'Active.')
                : new CheckResult($id, CheckResult::STATUS_OK, 'two_factor_module_on_users', 'Active; [count] backend users have set up a second factor.', ['count' => (string) $users]);
        }

        if ($users === null) {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'two_factor_module_off_unknown', 'Inactive: the login does not ask for codes. Could not determine whether backend users have set up a second factor.');
        }

        if ($users > 0) {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'two_factor_module_off_enrolled', 'Inactive, although [count] backend users have set up a second factor: their login does not ask for the code. Switch the module on again unless this is an emergency.', ['count' => (string) $users]);
        }

        return new CheckResult($id, CheckResult::STATUS_INFO, 'two_factor_module_off_unused', 'Inactive. No backend user has set up a second factor.');
    }

    private function shopwareVersion(string $version): CheckResult
    {
        $id = 'shopware_version';
        $placeholders = ['version' => $version, 'latest' => self::LATEST_SHOPWARE_VERSION];

        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $match) !== 1) {
            return new CheckResult($id, CheckResult::STATUS_INFO, 'shopware_version_unknown', 'The Shopware version could not be determined.');
        }

        if ($version === self::LATEST_SHOPWARE_VERSION) {
            return new CheckResult($id, CheckResult::STATUS_OK, 'shopware_version_latest', 'Shopware [version] is the latest Shopware 5 version. Shopware 5 itself no longer receives updates from the manufacturer.', $placeholders);
        }

        if ($match[1] === '5' && \in_array($match[2], ['6', '7'], true) && version_compare($version, self::LATEST_SHOPWARE_VERSION, '<')) {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'shopware_version_outdated', 'Shopware [version] lacks security fixes of later versions. Update to Shopware [latest].', $placeholders);
        }

        return new CheckResult($id, CheckResult::STATUS_INFO, 'shopware_version_untested', 'Shopware [version] has not been tested with this plugin.', $placeholders);
    }

    private function phpVersion(string $version, DateTimeImmutable $today): CheckResult
    {
        $id = 'php_version';

        if (preg_match('/^(\d+)\.(\d+)/', $version, $match) !== 1) {
            return new CheckResult($id, CheckResult::STATUS_INFO, 'php_version_unknown', 'No security support dates are known for PHP [version].', ['version' => $version]);
        }

        $branch = $match[1] . '.' . $match[2];
        if (version_compare($branch, '7.4', '<')) {
            return new CheckResult($id, CheckResult::STATUS_ERROR, 'php_version_unsupported', 'PHP [version] has not received security updates for years. Update PHP.', ['version' => $version]);
        }

        if (!isset(self::PHP_SECURITY_SUPPORT_UNTIL[$branch])) {
            return new CheckResult($id, CheckResult::STATUS_INFO, 'php_version_unknown', 'No security support dates are known for PHP [version].', ['version' => $version]);
        }

        $until = self::PHP_SECURITY_SUPPORT_UNTIL[$branch];
        $placeholders = ['version' => $version, 'date' => $until];
        $today = $today->format('Y-m-d');

        if ($today > $until) {
            return new CheckResult($id, CheckResult::STATUS_ERROR, 'php_version_eol', 'PHP [version] has not received security updates since [date]. Update PHP or have your host confirm extended security support in writing.', $placeholders);
        }

        $warnFrom = (new DateTimeImmutable($until))->modify('-' . self::PHP_WARNING_DAYS . ' days')->format('Y-m-d');
        if ($today >= $warnFrom) {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'php_version_eol_soon', 'Security support for PHP [version] ends on [date]. Plan the PHP update or extended support with your host now.', $placeholders);
        }

        return new CheckResult($id, CheckResult::STATUS_OK, 'php_version_supported', 'PHP [version] receives security updates until [date].', $placeholders);
    }

    private function uploadProgress(?bool $enabled): CheckResult
    {
        $id = 'upload_progress';

        if ($enabled === null) {
            return new CheckResult($id, CheckResult::STATUS_INFO, 'upload_progress_unknown', 'The PHP setting session.upload_progress.enabled could not be read.');
        }

        if ($enabled) {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'upload_progress_enabled', 'session.upload_progress.enabled is on. Shopware does not need it; set it to 0 in the PHP configuration unless another application uses it.');
        }

        return new CheckResult($id, CheckResult::STATUS_OK, 'upload_progress_disabled', 'session.upload_progress.enabled is off.');
    }

    private function trustedProxies(Environment $environment): CheckResult
    {
        $id = 'trusted_proxies';

        $tooBroad = array_values(array_filter(
            $environment->trustedProxies,
            fn (string $entry): bool => $this->trustsEveryClient($entry, $environment->remoteAddrKeywordTrustsClient),
        ));
        if ($tooBroad !== []) {
            return new CheckResult($id, CheckResult::STATUS_ERROR, 'trusted_proxies_too_broad', '“trustedproxies” in config.php trusts every client ([entries]). Host, protocol and client IP can then be forged with forwarding headers. Enter only the addresses of your proxies or load balancers.', ['entries' => implode(', ', $tooBroad)]);
        }

        if ($environment->forwardedHeadersFromUntrustedSource === true) {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'trusted_proxies_untrusted_forwarding', 'This request contains forwarding headers (Forwarded, X-Forwarded-*) from an address not listed in “trustedproxies”; Shopware ignores them. If the shop runs behind a proxy or load balancer, enter its address in “trustedproxies” in config.php.');
        }

        if ($environment->trustedProxies === []) {
            return new CheckResult($id, CheckResult::STATUS_OK, 'trusted_proxies_none', 'No proxies are trusted; forwarding headers are ignored.');
        }

        return new CheckResult($id, CheckResult::STATUS_OK, 'trusted_proxies_set', 'Trusted proxies: [entries].', ['entries' => implode(', ', $environment->trustedProxies)]);
    }

    private function trustsEveryClient(string $entry, bool $remoteAddrKeywordTrustsClient): bool
    {
        if ($entry === 'REMOTE_ADDR') {
            return $remoteAddrKeywordTrustsClient;
        }

        $parts = explode('/', $entry, 2);
        if (\count($parts) !== 2 || preg_match('/^\d{1,3}$/', $parts[1]) !== 1) {
            return false;
        }

        $prefixLength = (int) $parts[1];
        if (filter_var($parts[0], \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) !== false) {
            return $prefixLength < 8;
        }

        if (filter_var($parts[0], \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) !== false && $prefixLength < 16) {
            // Only ranges overlapping the global unicast space 2000::/3 contain
            // public clients; e.g. fc00::/7 (unique local) does not.
            $packed = inet_pton($parts[0]);
            $topBits = $packed === false ? 0 : \ord($packed[0]) >> 5;
            $compared = min($prefixLength, 3);

            return $compared === 0 || ($topBits >> (3 - $compared)) === (0b001 >> (3 - $compared));
        }

        return false;
    }

    /**
     * @param list<string> $shopsWithoutHost
     */
    private function shopHost(array $shopsWithoutHost): CheckResult
    {
        $id = 'shop_host';

        if ($shopsWithoutHost === []) {
            return new CheckResult($id, CheckResult::STATUS_OK, 'shop_host_set', 'All main shops have a host.');
        }

        return new CheckResult($id, CheckResult::STATUS_WARNING, 'shop_host_missing', 'Main shops without host ([shops]): Shopware takes the host from the request, so generated links can point to a host chosen by the client. Enter the host in the basic settings under Shops.', ['shops' => implode(', ', $shopsWithoutHost)]);
    }

    private function templateSandbox(bool $active): CheckResult
    {
        if ($active) {
            return new CheckResult('template_sandbox', CheckResult::STATUS_OK, 'template_sandbox_active', 'The Smarty security policy of Shopware is active.');
        }

        return new CheckResult('template_sandbox', CheckResult::STATUS_ERROR, 'template_sandbox_inactive', 'The Smarty security policy is not active. Check which plugin disables it.');
    }

    private function protectionFiles(Environment $environment): CheckResult
    {
        $id = 'protection_files';

        if ($environment->serverSoftware !== null && stripos($environment->serverSoftware, 'nginx') !== false) {
            return new CheckResult($id, CheckResult::STATUS_INFO, 'protection_files_nginx', 'nginx does not read .htaccess files. Make sure the server configuration denies access to: [directories].', ['directories' => implode(', ', array_keys(ProtectionFiles::DIRECTORIES))]);
        }

        if ($environment->missingProtectionFiles === []) {
            return new CheckResult($id, CheckResult::STATUS_OK, 'protection_files_present', 'All protection files are present.');
        }

        return new CheckResult($id, CheckResult::STATUS_WARNING, 'protection_files_missing', 'Missing protection files: [files]. Under Apache these directories may be readable from outside. Restore them with the button below this table or with “bin/console sw5cs:protection-files:restore”.', ['files' => implode(', ', $environment->missingProtectionFiles)]);
    }

    private function installer(Environment $environment): CheckResult
    {
        $id = 'installer';

        if (!$environment->installerPresent) {
            return new CheckResult($id, CheckResult::STATUS_OK, 'installer_absent', 'The directory recovery/install/ does not exist.');
        }

        if (!$environment->installerLocked) {
            return new CheckResult($id, CheckResult::STATUS_ERROR, 'installer_unlocked', 'The installer in recovery/install/ is not locked (recovery/install/data/install.lock is missing) and can be run again. Remove the directory or restore the lock file.');
        }

        return new CheckResult($id, CheckResult::STATUS_WARNING, 'installer_present', 'The directory recovery/install/ still exists. The installer is locked, but its pages remain reachable (CVE-2022-48150). Remove the directory or deny access to it.');
    }

    private function passwordResetCaptcha(Environment $environment): CheckResult
    {
        $id = 'password_reset_captcha';
        $method = $environment->passwordResetCaptcha;

        if ($method === null) {
            return new CheckResult($id, CheckResult::STATUS_INFO, 'password_reset_captcha_unavailable', 'This Shopware version has no captcha setting for “Forgot password”.');
        }

        if ($method === '' || $method === 'nocaptcha') {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'password_reset_captcha_off', 'The “Forgot password” form has no captcha. Select one in the basic settings under Password reset.');
        }

        if ($environment->captchaColor === null || trim($environment->captchaColor) === '') {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'password_reset_captcha_unchecked', 'A captcha is selected, but Shopware only checks it if the captcha font colour (captchaColor) is set. Enter a colour, e.g. 51,51,51.');
        }

        return new CheckResult($id, CheckResult::STATUS_OK, 'password_reset_captcha_on', 'The “Forgot password” form is protected by a captcha ([method]).', ['method' => $method]);
    }

    private function apiAccess(Environment $environment, PluginConfig $config): CheckResult
    {
        $id = 'api_access';
        $policy = new ApiAccessPolicy($config->apiAllowedAddresses);

        if ($policy->invalidEntries() !== []) {
            return new CheckResult($id, CheckResult::STATUS_ERROR, 'api_access_invalid', 'The allowed addresses for the REST API contain invalid entries ([entries]). They allow nothing; correct them in the plugin configuration.', ['entries' => implode(', ', $policy->invalidEntries())]);
        }

        if ($policy->wideEntries() !== []) {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'api_access_too_wide', 'The allowed addresses for the REST API contain ranges that admit nearly every client ([entries]). Enter only the addresses or narrow ranges of your systems.', ['entries' => implode(', ', $policy->wideEntries())]);
        }

        if ($policy->isRestricted()) {
            return new CheckResult($id, CheckResult::STATUS_OK, 'api_access_restricted', 'The REST API only accepts requests from [count] configured addresses or ranges.', ['count' => (string) $policy->validEntryCount()]);
        }

        if ($environment->apiUsersWithKey === null) {
            return new CheckResult($id, CheckResult::STATUS_INFO, 'api_access_unknown', 'Could not determine which backend users have an API key.');
        }

        if ($environment->apiUsersWithKey === 0) {
            return new CheckResult($id, CheckResult::STATUS_OK, 'api_access_no_keys', 'No active backend user has an API key.');
        }

        return new CheckResult($id, CheckResult::STATUS_WARNING, 'api_access_open', '[count] active backend users have an API key. The REST API accepts it from any address and without two-factor login. Restrict it to the addresses of your systems (plugin setting “REST API: allowed addresses”) or remove unused keys.', ['count' => (string) $environment->apiUsersWithKey]);
    }

    /**
     * Shopware's own input filter for the storefront (SQL, XSS, remote file
     * inclusion, strip_tags); the request hardening of this plugin adds to it.
     */
    private function inputFilter(Environment $environment): CheckResult
    {
        $id = 'input_filter';

        if ($environment->inputFilterActive === null || $environment->inputFilterDisabled === null) {
            return new CheckResult($id, CheckResult::STATUS_INFO, 'input_filter_unknown', 'The settings of Shopware\'s input filter could not be read.');
        }

        if (!$environment->inputFilterActive) {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'input_filter_inactive', 'Shopware\'s input filter (plugin “InputFilter”) is inactive. Activate it in the plugin manager.');
        }

        if ($environment->inputFilterDisabled !== []) {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'input_filter_weakened', 'Shopware\'s input filter is active, but these protections are switched off: [settings]. Switch them on in the basic settings.', ['settings' => implode(', ', $environment->inputFilterDisabled)]);
        }

        return new CheckResult($id, CheckResult::STATUS_OK, 'input_filter_on', 'Shopware\'s input filter is active with all protections.');
    }

    /**
     * CVE-2026-82531 (Smarty < 4.5.8) needs Smarty's output cache, which
     * Shopware never switches on.
     */
    private function smartyOutputCache(Environment $environment): CheckResult
    {
        $id = 'smarty_output_cache';

        if ($environment->smartyCacheActive) {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'smarty_cache_active', 'Smarty\'s output cache is switched on. Smarty 3.1.12 is affected by CVE-2026-82531 when it is on. Switch it off unless a plugin strictly needs it.');
        }

        if ($environment->smartyCachePlugins === null) {
            return new CheckResult($id, CheckResult::STATUS_INFO, 'smarty_cache_unknown', 'The plugin directories could not be checked for Smarty\'s output cache.');
        }

        if ($environment->smartyCachePlugins !== []) {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'smarty_cache_plugins', 'These plugins switch on Smarty\'s output cache in their code: [plugins]. Smarty 3.1.12 is affected by CVE-2026-82531 when it is on. Check whether the plugins need it and keep them up to date.', ['plugins' => implode(', ', $environment->smartyCachePlugins)]);
        }

        if (!$environment->smartyCacheScanComplete) {
            return new CheckResult($id, CheckResult::STATUS_INFO, 'smarty_cache_incomplete', 'The plugin directories could only be checked in part for Smarty\'s output cache (unreadable directories or files, or more than [limit] files). None of the checked plugins switches it on.', ['limit' => (string) SmartyCacheScanner::MAX_FILES]);
        }

        return new CheckResult($id, CheckResult::STATUS_OK, 'smarty_cache_off', 'Smarty\'s output cache is off, and no plugin switches it on in its code.');
    }

    /**
     * @return list<CheckResult>
     */
    private function modules(PluginConfig $config): array
    {
        return [
            $config->securityHeadersEnabled
                ? new CheckResult('module_security_headers', CheckResult::STATUS_OK, 'module_on', 'Active.')
                : new CheckResult('module_security_headers', CheckResult::STATUS_WARNING, 'module_off', 'Inactive. Enable it in the plugin configuration.'),
            $this->cspModule($config),
            $this->templateGuardModule($config),
            $config->contentGuardEnabled
                ? new CheckResult('module_content_guard', CheckResult::STATUS_OK, 'module_on', 'Active.')
                : new CheckResult('module_content_guard', CheckResult::STATUS_WARNING, 'module_off', 'Inactive. Enable it in the plugin configuration.'),
            $config->requestGuardEnabled
                ? new CheckResult('module_request_guard', CheckResult::STATUS_OK, 'module_on', 'Active.')
                : new CheckResult('module_request_guard', CheckResult::STATUS_WARNING, 'module_off', 'Inactive. Enable it in the plugin configuration.'),
            $config->uploadGuardEnabled
                ? new CheckResult('module_upload_guard', CheckResult::STATUS_OK, 'module_on', 'Active.')
                : new CheckResult('module_upload_guard', CheckResult::STATUS_WARNING, 'module_off', 'Inactive. Enable it in the plugin configuration.'),
            $config->javaScriptEscapeEnabled
                ? new CheckResult('module_javascript_escape', CheckResult::STATUS_OK, 'module_on', 'Active.')
                : new CheckResult('module_javascript_escape', CheckResult::STATUS_WARNING, 'module_off', 'Inactive. Enable it in the plugin configuration.'),
        ];
    }

    private function cspModule(PluginConfig $config): CheckResult
    {
        $id = 'module_csp';

        if ($config->cspMode === PluginConfig::OFF) {
            return new CheckResult($id, CheckResult::STATUS_INFO, 'module_csp_off', 'Off. Test a policy in the mode “Report only” first.');
        }

        if (!$config->securityHeadersEnabled) {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'module_csp_headers_off', 'Configured, but not sent because the security headers module is inactive.');
        }

        if ($config->cspFrontendPolicy === null || ($config->securityHeadersInBackend && $config->cspBackendPolicy === null)) {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'module_csp_invalid', 'A configured policy is invalid and is not sent. Check the plugin configuration.');
        }

        if ($config->cspMode === PluginConfig::CSP_MODE_REPORT_ONLY) {
            return new CheckResult($id, CheckResult::STATUS_INFO, 'module_csp_report_only', 'Report only: violations are reported, nothing is blocked.');
        }

        return new CheckResult($id, CheckResult::STATUS_OK, 'module_csp_enforce', 'Enforced.');
    }

    private function templateGuardModule(PluginConfig $config): CheckResult
    {
        $id = 'module_template_guard';

        if (!$config->templateGuardEnabled) {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'module_off', 'Inactive. Enable it in the plugin configuration.');
        }

        if (!$config->templateGuardStrictIncludeMath) {
            return new CheckResult($id, CheckResult::STATUS_WARNING, 'module_template_guard_relaxed', 'Active, but without the strict rules for include and math.');
        }

        return new CheckResult($id, CheckResult::STATUS_OK, 'module_on', 'Active.');
    }
}
