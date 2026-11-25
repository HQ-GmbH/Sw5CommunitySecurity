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

namespace Sw5CommunitySecurity\Components\ProtectionFiles;

use Psr\Log\LoggerInterface;
use Sw5CommunitySecurity\Components\Config\DefaultShopConfigSource;
use Throwable;

/**
 * Restores missing protection files if the option "protectionFilesAutoRestore"
 * is enabled and logs the affected paths. Never throws: it runs while
 * Shopware clears caches (see Subscriber\ProtectionFileSubscriber).
 */
final class AutoRestore
{
    /**
     * @readonly
     */
    private DefaultShopConfigSource $config;

    /**
     * @readonly
     */
    private ProtectionFiles $protectionFiles;

    /**
     * @readonly
     */
    private string $shopwareRoot;

    /**
     * @readonly
     */
    private LoggerInterface $logger;

    public function __construct(DefaultShopConfigSource $config, ProtectionFiles $protectionFiles, string $shopwareRoot, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->protectionFiles = $protectionFiles;
        $this->shopwareRoot = $shopwareRoot;
        $this->logger = $logger;
    }

    public function run(): void
    {
        // Clearing caches must never fail because of this plugin.
        try {
            if (!$this->config->getForDefaultShop()->protectionFilesAutoRestore) {
                return;
            }

            $result = $this->protectionFiles->restore($this->shopwareRoot);
        } catch (Throwable $exception) {
            $this->logger->error('Checking protection files failed.', ['exception' => \get_class($exception)]);

            return;
        }

        if ($result->restored !== []) {
            $this->logger->warning('Missing protection files were restored.', ['files' => $result->restored]);
        }

        if ($result->failed !== []) {
            $this->logger->error('Protection files could not be created.', ['files' => $result->failed]);
        }
    }
}
