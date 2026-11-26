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

/**
 * Snapshot of the shop environment, filled by EnvironmentCollector.
 *
 * Plain data holder without Shopware dependencies, so SecurityCheck can be
 * unit-tested. Null means "could not be determined".
 */
final class Environment
{
    public string $shopwareVersion = '';

    public string $phpVersion = \PHP_VERSION;

    public ?bool $uploadProgressEnabled = null;

    /**
     * Entries of "trustedproxies" in config.php.
     *
     * @var list<string>
     */
    public array $trustedProxies = [];

    /**
     * True if the keyword REMOTE_ADDR in "trustedproxies" is resolved to the
     * address of the current client (Symfony 4.4, Shopware 5.7), i.e. every
     * client is trusted.
     */
    public bool $remoteAddrKeywordTrustsClient = false;

    /**
     * True if the current request carries forwarding headers (Forwarded,
     * X-Forwarded-*) from an address that is not a trusted proxy. Null
     * outside of a web request.
     */
    public ?bool $forwardedHeadersFromUntrustedSource = null;

    /**
     * Names of main shops without a host.
     *
     * @var list<string>
     */
    public array $shopsWithoutHost = [];

    public bool $templateSandboxActive = false;

    /**
     * Relative paths of protection files (.htaccess) missing in existing
     * directories.
     *
     * @var list<string>
     */
    public array $missingProtectionFiles = [];

    /**
     * SERVER_SOFTWARE of the current web request, null outside of it.
     */
    public ?string $serverSoftware = null;

    public bool $installerPresent = false;

    public bool $installerLocked = false;

    /**
     * Value of "passwordResetCaptcha"; null if the setting does not exist
     * (Shopware versions without it).
     */
    public ?string $passwordResetCaptcha = null;

    public ?string $captchaColor = null;

    /**
     * Active backend users with an API key; null if unknown.
     */
    public ?int $apiUsersWithKey = null;

    /**
     * Smarty's output cache switched on for the template service.
     */
    public bool $smartyCacheActive = false;

    /**
     * Plugins that switch on Smarty's output cache in their code; null if not scanned.
     *
     * @var list<string>|null
     */
    public ?array $smartyCachePlugins = null;

    /**
     * Shopware's input filter plugin (InputFilter) is active; null if unknown.
     */
    public ?bool $inputFilterActive = null;

    /**
     * Protections of the input filter that are switched off (sql_protection,
     * xss_protection, rfi_protection, strip_tags); null if unknown.
     *
     * @var list<string>|null
     */
    public ?array $inputFilterDisabled = null;
}
