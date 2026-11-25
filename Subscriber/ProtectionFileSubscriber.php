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

namespace Sw5CommunitySecurity\Subscriber;

use Enlight\Event\SubscriberInterface;
use Sw5CommunitySecurity\Components\ProtectionFiles\AutoRestore;

/**
 * Restores missing protection files whenever Shopware clears the HTTP cache
 * (CacheManager::clearHttpCache(), e.g. backend "Clear caches" with the
 * frontend or HTTP cache selected, and plugin installation). This event is
 * the regular maintenance point Shopware offers; "sw:cache:clear" on the
 * console only removes the kernel cache directory and does not fire it.
 */
final class ProtectionFileSubscriber implements SubscriberInterface
{
    /**
     * @readonly
     */
    private AutoRestore $autoRestore;

    public function __construct(AutoRestore $autoRestore)
    {
        $this->autoRestore = $autoRestore;
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Shopware_Plugins_HttpCache_ClearCache' => 'onClearHttpCache',
        ];
    }

    public function onClearHttpCache(): void
    {
        $this->autoRestore->run();
    }
}
