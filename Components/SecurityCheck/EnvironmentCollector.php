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

use Shopware_Components_Config;
use Smarty;
use Smarty_Security;
use Sw5CommunitySecurity\Components\Database\RowReader;
use Sw5CommunitySecurity\Components\ProtectionFiles\ProtectionFiles;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * Reads the environment snapshot from Shopware. Read-only.
 *
 * Sources (Shopware 5.6.10 and 5.7.20): container parameters are the
 * config.php values prefixed with "shopware." (Kernel::addShopwareConfig()),
 * the release version is "shopware.release.version"
 * (Kernel::getKernelParameters()), the Smarty security policy is set in
 * Components/DependencyInjection/Bridge/Template.php.
 */
final class EnvironmentCollector
{
    /**
     * Forwarding headers evaluated by Symfony (Request::HEADER_*).
     */
    private const FORWARDING_HEADERS = ['FORWARDED', 'X_FORWARDED_FOR', 'X_FORWARDED_HOST', 'X_FORWARDED_PROTO', 'X_FORWARDED_PORT'];

    /**
     * @readonly
     */
    private ContainerInterface $container;

    /**
     * @readonly
     */
    private RowReader $rows;

    public function __construct(ContainerInterface $container, RowReader $rows)
    {
        $this->container = $container;
        $this->rows = $rows;
    }

    public function collect(?Request $request): Environment
    {
        $environment = new Environment();
        $environment->shopwareVersion = $this->stringParameter('shopware.release.version');
        $environment->uploadProgressEnabled = $this->iniFlag('session.upload_progress.enabled');

        $environment->trustedProxies = $this->trustedProxies();
        $environment->remoteAddrKeywordTrustsClient = \in_array('REMOTE_ADDR', $environment->trustedProxies, true)
            && !\in_array('REMOTE_ADDR', Request::getTrustedProxies(), true);

        if ($request !== null) {
            $environment->forwardedHeadersFromUntrustedSource = $this->hasUntrustedForwardingHeaders($request);
            $serverSoftware = $request->server->get('SERVER_SOFTWARE');
            $environment->serverSoftware = \is_string($serverSoftware) ? $serverSoftware : null;
        }

        $environment->shopsWithoutHost = $this->shopsWithoutHost();
        $environment->templateSandboxActive = $this->templateSandboxActive();
        $environment->smartyCacheActive = $this->smartyCacheActive();

        $root = rtrim($this->stringParameter('kernel.root_dir'), '/');
        if ($root !== '') {
            $environment->missingProtectionFiles = (new ProtectionFiles())->missing($root);
            $environment->installerPresent = is_dir($root . '/recovery/install');
            $environment->smartyCachePlugins = (new SmartyCacheScanner())->pluginsEnablingCache($root);
            $environment->installerLocked = is_file($root . '/recovery/install/data/install.lock');
        }

        $environment->passwordResetCaptcha = $this->configValue('passwordResetCaptcha');
        $environment->apiUsersWithKey = $this->apiUsersWithKey();
        $this->inputFilter($environment);
        $environment->captchaColor = $this->configValue('captchaColor');

        return $environment;
    }

    private function stringParameter(string $name): string
    {
        if (!$this->container->hasParameter($name)) {
            return '';
        }

        $value = $this->container->getParameter($name);

        return \is_string($value) ? $value : '';
    }

    private function iniFlag(string $name): ?bool
    {
        $value = \ini_get($name);
        if ($value === false) {
            return null;
        }

        return filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
    }

    /**
     * @return list<string>
     */
    private function trustedProxies(): array
    {
        if (!$this->container->hasParameter('shopware.trustedproxies')) {
            return [];
        }

        $value = $this->container->getParameter('shopware.trustedproxies');
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    private function hasUntrustedForwardingHeaders(Request $request): bool
    {
        $remoteAddress = $request->server->get('REMOTE_ADDR');
        if (\is_string($remoteAddress) && Request::getTrustedProxies() !== [] && IpUtils::checkIp($remoteAddress, Request::getTrustedProxies())) {
            return false;
        }

        foreach (self::FORWARDING_HEADERS as $header) {
            if ($request->server->has('HTTP_' . $header)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function shopsWithoutHost(): array
    {
        $names = [];
        foreach ($this->rows->fetchAll("SELECT name FROM s_core_shops WHERE main_id IS NULL AND (host IS NULL OR host = '') ORDER BY id") as $row) {
            $names[] = \is_string($row['name'] ?? null) ? $row['name'] : '';
        }

        return $names;
    }

    /**
     * Shopware's input filter (engine/Shopware/Plugins/Default/Frontend/InputFilter,
     * form elements sql_protection, xss_protection, rfi_protection, strip_tags,
     * all on by default). Values of the default shop, otherwise the defaults.
     */
    private function inputFilter(Environment $environment): void
    {
        try {
            $plugin = $this->rows->fetchAll("SELECT active FROM s_core_plugins WHERE name = 'InputFilter' AND namespace = 'Frontend'");
            if ($plugin === []) {
                return;
            }
            $environment->inputFilterActive = (bool) ($plugin[0]['active'] ?? false);

            $rows = $this->rows->fetchAll(
                'SELECT e.name, e.value AS default_value, v.value AS shop_value FROM s_core_config_elements e'
                . ' INNER JOIN s_core_config_forms f ON f.id = e.form_id'
                . ' INNER JOIN s_core_plugins p ON p.id = f.plugin_id'
                . ' LEFT JOIN s_core_config_values v ON v.element_id = e.id AND v.shop_id = 1'
                . " WHERE p.name = 'InputFilter' AND e.name IN ('sql_protection', 'xss_protection', 'rfi_protection', 'strip_tags')"
                . ' ORDER BY e.name',
            );
        } catch (Throwable $exception) {
            return;
        }

        $disabled = [];
        foreach ($rows as $row) {
            $raw = $row['shop_value'] ?? $row['default_value'] ?? null;
            $value = \is_string($raw) ? @unserialize($raw, ['allowed_classes' => false]) : null;
            if ($value === false || $value === 0 || $value === '0') {
                $disabled[] = \is_string($row['name'] ?? null) ? $row['name'] : '';
            }
        }
        $environment->inputFilterDisabled = $disabled;
    }

    private function apiUsersWithKey(): ?int
    {
        try {
            $rows = $this->rows->fetchAll("SELECT COUNT(*) AS users FROM s_core_auth WHERE active = 1 AND apiKey IS NOT NULL AND apiKey <> ''");
        } catch (Throwable $exception) {
            return null;
        }
        $count = $rows[0]['users'] ?? null;

        return is_numeric($count) ? (int) $count : null;
    }

    private function smartyCacheActive(): bool
    {
        try {
            $template = $this->container->get('template');
        } catch (Throwable $exception) {
            return false;
        }

        return $template instanceof Smarty && (bool) $template->caching;
    }

    private function templateSandboxActive(): bool
    {
        try {
            $template = $this->container->get('template');
        } catch (Throwable $exception) {
            return false;
        }

        return $template instanceof Smarty && $template->security_policy instanceof Smarty_Security;
    }

    private function configValue(string $name): ?string
    {
        try {
            $config = $this->container->get('config');
            $value = $config instanceof Shopware_Components_Config ? $config->get($name) : null;
        } catch (Throwable $exception) {
            return null;
        }

        return \is_string($value) ? $value : null;
    }
}
