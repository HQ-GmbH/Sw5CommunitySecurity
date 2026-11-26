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

namespace Sw5CommunitySecurity\Tests\Unit\Components\SecurityCheck;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sw5CommunitySecurity\Components\Config\PluginConfig;
use Sw5CommunitySecurity\Components\Messages\DefaultMessageCatalog;
use Sw5CommunitySecurity\Components\Messages\MessageFormatter;
use Sw5CommunitySecurity\Components\SecurityCheck\CheckResult;
use Sw5CommunitySecurity\Components\SecurityCheck\Environment;
use Sw5CommunitySecurity\Components\SecurityCheck\SecurityCheck;
use Sw5CommunitySecurity\Components\SecurityCheck\SecurityCheckRunner;

final class SecurityCheckTest extends TestCase
{
    private const TODAY = '2026-06-01';

    private const SNIPPET_FILE = __DIR__ . '/../../../../Resources/snippets/backend/sw5_community_security/messages.ini';

    public function testSafeEnvironmentHasNoWarnings(): void
    {
        $results = self::evaluate(self::safeEnvironment());

        static::assertSame(array_keys(SecurityCheck::TITLES), array_map(static fn (CheckResult $result): string => $result->id, $results));
        foreach ($results as $result) {
            static::assertContains($result->status, [CheckResult::STATUS_OK, CheckResult::STATUS_INFO], $result->id . ': ' . $result->messageKey);
        }
    }

    /**
     * @return iterable<string, array{string, CheckResult::STATUS_*, string}>
     */
    public static function shopwareVersionProvider(): iterable
    {
        yield 'latest' => ['5.7.20', CheckResult::STATUS_OK, 'shopware_version_latest'];
        yield '5.7.19' => ['5.7.19', CheckResult::STATUS_WARNING, 'shopware_version_outdated'];
        yield '5.6.10' => ['5.6.10', CheckResult::STATUS_WARNING, 'shopware_version_outdated'];
        yield 'unknown future version' => ['5.8.0', CheckResult::STATUS_INFO, 'shopware_version_untested'];
        yield 'placeholder of a source checkout' => ['___VERSION___', CheckResult::STATUS_INFO, 'shopware_version_unknown'];
        yield 'empty' => ['', CheckResult::STATUS_INFO, 'shopware_version_unknown'];
    }

    /**
     * @param CheckResult::STATUS_* $status
     */
    #[DataProvider('shopwareVersionProvider')]
    public function testShopwareVersion(string $version, string $status, string $messageKey): void
    {
        $environment = self::safeEnvironment();
        $environment->shopwareVersion = $version;

        self::assertResult(self::evaluate($environment), 'shopware_version', $status, $messageKey);
    }

    /**
     * @return iterable<string, array{string, string, CheckResult::STATUS_*, string}>
     */
    public static function phpVersionProvider(): iterable
    {
        yield '8.1 after end of support' => ['8.1.30', '2026-01-01', CheckResult::STATUS_ERROR, 'php_version_eol'];
        yield '8.1 on the last day' => ['8.1.30', '2025-12-31', CheckResult::STATUS_WARNING, 'php_version_eol_soon'];
        yield '8.2 more than 90 days before the end' => ['8.2.29', '2026-10-01', CheckResult::STATUS_OK, 'php_version_supported'];
        yield '8.2 exactly 90 days before the end' => ['8.2.29', '2026-10-02', CheckResult::STATUS_WARNING, 'php_version_eol_soon'];
        yield '8.2 after the end' => ['8.2.29', '2027-01-01', CheckResult::STATUS_ERROR, 'php_version_eol'];
        yield '7.4' => ['7.4.33', self::TODAY, CheckResult::STATUS_ERROR, 'php_version_eol'];
        yield '7.3' => ['7.3.33', self::TODAY, CheckResult::STATUS_ERROR, 'php_version_unsupported'];
        yield 'unknown future branch' => ['9.0.0', self::TODAY, CheckResult::STATUS_INFO, 'php_version_unknown'];
        yield 'unparsable' => ['unknown', self::TODAY, CheckResult::STATUS_INFO, 'php_version_unknown'];
    }

    /**
     * @param CheckResult::STATUS_* $status
     */
    #[DataProvider('phpVersionProvider')]
    public function testPhpVersion(string $version, string $today, string $status, string $messageKey): void
    {
        $environment = self::safeEnvironment();
        $environment->phpVersion = $version;

        self::assertResult(self::evaluate($environment, null, $today), 'php_version', $status, $messageKey);
    }

    public function testPhpEndOfSupportIsNamed(): void
    {
        $environment = self::safeEnvironment();
        $environment->phpVersion = '8.2.29';

        $result = self::find(self::evaluate($environment, null, '2026-11-24'), 'php_version');
        static::assertSame(['version' => '8.2.29', 'date' => '2026-12-31'], $result->placeholders);
    }

    public function testUploadProgress(): void
    {
        $environment = self::safeEnvironment();

        $environment->uploadProgressEnabled = true;
        self::assertResult(self::evaluate($environment), 'upload_progress', CheckResult::STATUS_WARNING, 'upload_progress_enabled');

        $environment->uploadProgressEnabled = null;
        self::assertResult(self::evaluate($environment), 'upload_progress', CheckResult::STATUS_INFO, 'upload_progress_unknown');
    }

    /**
     * @return iterable<string, array{list<string>, bool, CheckResult::STATUS_*, string}>
     */
    public static function trustedProxiesProvider(): iterable
    {
        yield 'none' => [[], false, CheckResult::STATUS_OK, 'trusted_proxies_none'];
        yield 'single proxy' => [['10.0.0.5'], false, CheckResult::STATUS_OK, 'trusted_proxies_set'];
        yield 'private network' => [['10.0.0.0/8', 'fd00::/8'], false, CheckResult::STATUS_OK, 'trusted_proxies_set'];
        yield 'every IPv4 address' => [['0.0.0.0/0'], false, CheckResult::STATUS_ERROR, 'trusted_proxies_too_broad'];
        yield 'half of IPv4' => [['10.0.0.5', '128.0.0.0/1'], false, CheckResult::STATUS_ERROR, 'trusted_proxies_too_broad'];
        yield 'every IPv6 address' => [['::/0'], false, CheckResult::STATUS_ERROR, 'trusted_proxies_too_broad'];
        yield 'all public IPv6 addresses' => [['2000::/3'], false, CheckResult::STATUS_ERROR, 'trusted_proxies_too_broad'];
        yield 'large public IPv6 range' => [['2a00::/12'], false, CheckResult::STATUS_ERROR, 'trusted_proxies_too_broad'];
        yield 'IPv6 provider prefix' => [['2a01:4f8::/32'], false, CheckResult::STATUS_OK, 'trusted_proxies_set'];
        yield 'REMOTE_ADDR resolved to the client' => [['REMOTE_ADDR'], true, CheckResult::STATUS_ERROR, 'trusted_proxies_too_broad'];
        yield 'REMOTE_ADDR not supported' => [['REMOTE_ADDR'], false, CheckResult::STATUS_OK, 'trusted_proxies_set'];
    }

    /**
     * @param list<string>          $proxies
     * @param CheckResult::STATUS_* $status
     */
    #[DataProvider('trustedProxiesProvider')]
    public function testTrustedProxies(array $proxies, bool $remoteAddrKeywordTrustsClient, string $status, string $messageKey): void
    {
        $environment = self::safeEnvironment();
        $environment->trustedProxies = $proxies;
        $environment->remoteAddrKeywordTrustsClient = $remoteAddrKeywordTrustsClient;

        self::assertResult(self::evaluate($environment), 'trusted_proxies', $status, $messageKey);
    }

    public function testTooBroadProxiesAreNamed(): void
    {
        $environment = self::safeEnvironment();
        $environment->trustedProxies = ['10.0.0.5', '0.0.0.0/0'];

        static::assertSame(['entries' => '0.0.0.0/0'], self::find(self::evaluate($environment), 'trusted_proxies')->placeholders);
    }

    public function testForwardingHeadersFromUntrustedSource(): void
    {
        $environment = self::safeEnvironment();
        $environment->forwardedHeadersFromUntrustedSource = true;

        self::assertResult(self::evaluate($environment), 'trusted_proxies', CheckResult::STATUS_WARNING, 'trusted_proxies_untrusted_forwarding');
    }

    public function testShopsWithoutHost(): void
    {
        $environment = self::safeEnvironment();
        $environment->shopsWithoutHost = ['Demoshop', 'B2B'];

        $result = self::assertResult(self::evaluate($environment), 'shop_host', CheckResult::STATUS_WARNING, 'shop_host_missing');
        static::assertSame(['shops' => 'Demoshop, B2B'], $result->placeholders);
    }

    public function testTemplateSandbox(): void
    {
        $environment = self::safeEnvironment();
        $environment->templateSandboxActive = false;

        self::assertResult(self::evaluate($environment), 'template_sandbox', CheckResult::STATUS_ERROR, 'template_sandbox_inactive');
    }

    public function testProtectionFiles(): void
    {
        $environment = self::safeEnvironment();
        $environment->missingProtectionFiles = ['vendor/.htaccess'];
        self::assertResult(self::evaluate($environment), 'protection_files', CheckResult::STATUS_WARNING, 'protection_files_missing');

        $environment->serverSoftware = 'nginx/1.24.0';
        self::assertResult(self::evaluate($environment), 'protection_files', CheckResult::STATUS_INFO, 'protection_files_nginx');
    }

    public function testInstaller(): void
    {
        $environment = self::safeEnvironment();
        $environment->installerPresent = true;
        $environment->installerLocked = true;
        self::assertResult(self::evaluate($environment), 'installer', CheckResult::STATUS_WARNING, 'installer_present');

        $environment->installerLocked = false;
        self::assertResult(self::evaluate($environment), 'installer', CheckResult::STATUS_ERROR, 'installer_unlocked');
    }

    /**
     * @return iterable<string, array{?string, ?string, CheckResult::STATUS_*, string}>
     */
    public static function captchaProvider(): iterable
    {
        yield 'default captcha' => ['default', '51,51,51', CheckResult::STATUS_OK, 'password_reset_captcha_on'];
        yield 'no captcha (Shopware default)' => ['nocaptcha', '51,51,51', CheckResult::STATUS_WARNING, 'password_reset_captcha_off'];
        yield 'empty' => ['', '51,51,51', CheckResult::STATUS_WARNING, 'password_reset_captcha_off'];
        yield 'colour missing' => ['default', ' ', CheckResult::STATUS_WARNING, 'password_reset_captcha_unchecked'];
        yield 'setting not available' => [null, '51,51,51', CheckResult::STATUS_INFO, 'password_reset_captcha_unavailable'];
    }

    /**
     * @param CheckResult::STATUS_* $status
     */
    #[DataProvider('captchaProvider')]
    public function testPasswordResetCaptcha(?string $method, ?string $colour, string $status, string $messageKey): void
    {
        $environment = self::safeEnvironment();
        $environment->passwordResetCaptcha = $method;
        $environment->captchaColor = $colour;

        self::assertResult(self::evaluate($environment), 'password_reset_captcha', $status, $messageKey);
    }

    public function testDisabledModulesAreReported(): void
    {
        $results = self::evaluate(self::safeEnvironment(), [
            'securityHeadersEnabled' => false,
            'templateGuardEnabled' => false,
            'uploadGuardEnabled' => false,
            'javaScriptEscapeEnabled' => false,
            'contentGuardEnabled' => false,
        ]);

        foreach (['module_security_headers', 'module_template_guard', 'module_content_guard', 'module_upload_guard', 'module_javascript_escape'] as $id) {
            self::assertResult($results, $id, CheckResult::STATUS_WARNING, 'module_off');
        }
    }

    public function testRelaxedTemplateGuard(): void
    {
        self::assertResult(
            self::evaluate(self::safeEnvironment(), ['templateGuardStrictIncludeMath' => false]),
            'module_template_guard',
            CheckResult::STATUS_WARNING,
            'module_template_guard_relaxed',
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>, CheckResult::STATUS_*, string}>
     */
    public static function cspProvider(): iterable
    {
        yield 'off' => [[], CheckResult::STATUS_INFO, 'module_csp_off'];
        yield 'report only' => [['headerCspMode' => 'report-only'], CheckResult::STATUS_INFO, 'module_csp_report_only'];
        yield 'enforce' => [['headerCspMode' => 'enforce'], CheckResult::STATUS_OK, 'module_csp_enforce'];
        yield 'invalid frontend policy' => [['headerCspMode' => 'enforce', 'headerCspPolicy' => "default-src 'self', script-src *"], CheckResult::STATUS_WARNING, 'module_csp_invalid'];
        yield 'invalid backend policy, backend off' => [['headerCspMode' => 'enforce', 'headerCspBackendPolicy' => ''], CheckResult::STATUS_OK, 'module_csp_enforce'];
        yield 'invalid backend policy, backend on' => [['headerCspMode' => 'enforce', 'headerCspBackendPolicy' => '', 'securityHeadersInBackend' => true], CheckResult::STATUS_WARNING, 'module_csp_invalid'];
        yield 'headers module off' => [['headerCspMode' => 'enforce', 'securityHeadersEnabled' => false], CheckResult::STATUS_WARNING, 'module_csp_headers_off'];
    }

    /**
     * @param array<string, mixed>  $config
     * @param CheckResult::STATUS_* $status
     */
    #[DataProvider('cspProvider')]
    public function testCspModule(array $config, string $status, string $messageKey): void
    {
        self::assertResult(self::evaluate(self::safeEnvironment(), $config), 'module_csp', $status, $messageKey);
    }

    public function testApiAccess(): void
    {
        $environment = self::safeEnvironment();
        self::assertResult(self::evaluate($environment), 'api_access', CheckResult::STATUS_OK, 'api_access_no_keys');

        $environment->apiUsersWithKey = null;
        self::assertResult(self::evaluate($environment), 'api_access', CheckResult::STATUS_INFO, 'api_access_unknown');

        $environment->apiUsersWithKey = 3;
        $open = self::assertResult(self::evaluate($environment), 'api_access', CheckResult::STATUS_WARNING, 'api_access_open');
        static::assertSame(['count' => '3'], $open->placeholders);

        $restricted = self::assertResult(self::evaluate($environment, ['apiAllowedAddresses' => "192.0.2.10\n2001:db8::/32"]), 'api_access', CheckResult::STATUS_OK, 'api_access_restricted');
        static::assertSame(['count' => '2'], $restricted->placeholders);

        $invalid = self::assertResult(self::evaluate($environment, ['apiAllowedAddresses' => '192.0.2.10 192.0.2.300 example.org']), 'api_access', CheckResult::STATUS_ERROR, 'api_access_invalid');
        static::assertSame(['entries' => '192.0.2.300, example.org'], $invalid->placeholders);
    }

    public function testSmartyOutputCache(): void
    {
        $environment = self::safeEnvironment();
        self::assertResult(self::evaluate($environment), 'smarty_output_cache', CheckResult::STATUS_OK, 'smarty_cache_off');

        $environment->smartyCachePlugins = null;
        self::assertResult(self::evaluate($environment), 'smarty_output_cache', CheckResult::STATUS_INFO, 'smarty_cache_unknown');

        $environment->smartyCachePlugins = ['custom/plugins/ExampleA', 'custom/plugins/ExampleB'];
        $plugins = self::assertResult(self::evaluate($environment), 'smarty_output_cache', CheckResult::STATUS_WARNING, 'smarty_cache_plugins');
        static::assertSame(['plugins' => 'custom/plugins/ExampleA, custom/plugins/ExampleB'], $plugins->placeholders);

        $environment->smartyCacheActive = true;
        self::assertResult(self::evaluate($environment), 'smarty_output_cache', CheckResult::STATUS_WARNING, 'smarty_cache_active');
    }

    public function testRowsAreSortedBySeverityAndKeepTheirOrder(): void
    {
        $results = [
            new CheckResult('php_version', CheckResult::STATUS_OK, 'a', 'A'),
            new CheckResult('installer', CheckResult::STATUS_WARNING, 'b', 'B'),
            new CheckResult('shop_host', CheckResult::STATUS_INFO, 'c', 'C'),
            new CheckResult('template_sandbox', CheckResult::STATUS_ERROR, 'd', 'D [x]', ['x' => '<b>']),
            new CheckResult('upload_progress', CheckResult::STATUS_WARNING, 'e', 'E'),
        ];

        $rows = SecurityCheckRunner::toRows($results, new MessageFormatter(new DefaultMessageCatalog()));

        static::assertSame(['template_sandbox', 'installer', 'upload_progress', 'shop_host', 'php_version'], array_column($rows, 'id'));
        static::assertSame(['Template sandbox'], \array_slice(array_column($rows, 'title'), 0, 1));
        static::assertSame(['D <b>'], \array_slice(array_column($rows, 'message'), 0, 1), 'plain text; escaping is up to the output');
    }

    /**
     * Every message the checks can produce has an en_GB snippet with the
     * same text as the default in the code, and a de_DE translation.
     */
    public function testEveryMessageHasMatchingSnippets(): void
    {
        $snippets = parse_ini_file(self::SNIPPET_FILE, true, \INI_SCANNER_RAW);
        static::assertIsArray($snippets);
        static::assertIsArray($snippets['en_GB'] ?? null);
        static::assertIsArray($snippets['de_DE'] ?? null);

        $messages = [];
        foreach (self::allResults() as $result) {
            $messages[$result->messageKey] = $result->defaultMessage;
        }
        foreach (SecurityCheck::TITLES as $id => $title) {
            $messages['check_' . $id] = $title;
        }
        foreach (SecurityCheckRunner::LABELS as $key => $label) {
            $messages[$key] = $label;
        }

        foreach ($messages as $key => $default) {
            static::assertSame($default, $snippets['en_GB'][$key] ?? null, 'en_GB.' . $key);
            static::assertArrayHasKey($key, $snippets['de_DE'], 'de_DE.' . $key);
        }
    }

    /**
     * @return list<CheckResult>
     */
    private static function allResults(): array
    {
        $results = [];
        foreach ([self::safeEnvironment(), self::unsafeEnvironment()] as $environment) {
            foreach (['2020-01-01', '2026-12-01', '2030-01-01'] as $today) {
                foreach ([[], ['headerCspMode' => 'report-only'], ['headerCspMode' => 'enforce', 'headerCspPolicy' => ''], ['headerCspMode' => 'enforce', 'securityHeadersEnabled' => false], ['templateGuardStrictIncludeMath' => false]] as $config) {
                    $results = array_merge($results, self::evaluate($environment, $config, $today));
                }
            }
        }

        foreach (self::shopwareVersionProvider() as [$version]) {
            $environment = self::safeEnvironment();
            $environment->shopwareVersion = $version;
            $results = array_merge($results, self::evaluate($environment));
        }
        foreach (self::phpVersionProvider() as [$version, $today]) {
            $environment = self::safeEnvironment();
            $environment->phpVersion = $version;
            $results = array_merge($results, self::evaluate($environment, null, $today));
        }
        foreach (self::captchaProvider() as [$method, $colour]) {
            $environment = self::safeEnvironment();
            $environment->passwordResetCaptcha = $method;
            $environment->captchaColor = $colour;
            $results = array_merge($results, self::evaluate($environment));
        }

        $environment = self::unsafeEnvironment();
        $environment->installerLocked = false;
        $environment->serverSoftware = 'nginx';
        $environment->uploadProgressEnabled = null;
        $environment->trustedProxies = [];
        $results = array_merge($results, self::evaluate($environment));
        $environment->forwardedHeadersFromUntrustedSource = false;
        $environment->trustedProxies = ['10.0.0.5'];
        $results = array_merge($results, self::evaluate($environment));

        $environment = self::unsafeEnvironment();
        $environment->apiUsersWithKey = null;
        $environment->smartyCacheActive = false;
        $results = array_merge($results, self::evaluate($environment));
        $environment->smartyCachePlugins = null;
        $results = array_merge($results, self::evaluate($environment));
        $results = array_merge($results, self::evaluate($environment, ['apiAllowedAddresses' => '192.0.2.0/24']));

        return array_merge($results, self::evaluate($environment, ['apiAllowedAddresses' => '192.0.2.0/24 typo']));
    }

    private static function safeEnvironment(): Environment
    {
        $environment = new Environment();
        $environment->shopwareVersion = '5.7.20';
        $environment->phpVersion = '8.2.29';
        $environment->uploadProgressEnabled = false;
        $environment->templateSandboxActive = true;
        $environment->passwordResetCaptcha = 'default';
        $environment->captchaColor = '51,51,51';
        $environment->apiUsersWithKey = 0;
        $environment->smartyCachePlugins = [];

        return $environment;
    }

    private static function unsafeEnvironment(): Environment
    {
        $environment = new Environment();
        $environment->shopwareVersion = '5.7.10';
        $environment->phpVersion = '8.1.2';
        $environment->uploadProgressEnabled = true;
        $environment->trustedProxies = ['0.0.0.0/0'];
        $environment->forwardedHeadersFromUntrustedSource = true;
        $environment->shopsWithoutHost = ['Demoshop'];
        $environment->missingProtectionFiles = ['vendor/.htaccess'];
        $environment->installerPresent = true;
        $environment->installerLocked = true;
        $environment->passwordResetCaptcha = 'nocaptcha';
        $environment->apiUsersWithKey = 2;
        $environment->smartyCacheActive = true;
        $environment->smartyCachePlugins = ['custom/plugins/ExampleCache'];

        return $environment;
    }

    /**
     * @param array<string, mixed>|null $config raw plugin configuration; null for the defaults
     *
     * @return list<CheckResult>
     */
    private static function evaluate(Environment $environment, ?array $config = null, string $today = self::TODAY): array
    {
        return (new SecurityCheck())->evaluate($environment, PluginConfig::fromArray($config ?? []), new DateTimeImmutable($today));
    }

    /**
     * @param list<CheckResult> $results
     */
    private static function find(array $results, string $id): CheckResult
    {
        foreach ($results as $result) {
            if ($result->id === $id) {
                return $result;
            }
        }

        static::fail('No result for ' . $id);
    }

    /**
     * @param list<CheckResult>     $results
     * @param CheckResult::STATUS_* $status
     */
    private static function assertResult(array $results, string $id, string $status, string $messageKey): CheckResult
    {
        $result = self::find($results, $id);
        static::assertSame([$status, $messageKey], [$result->status, $result->messageKey], $id);

        return $result;
    }
}
