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
use Enlight_Controller_Action;
use Enlight_Controller_ActionEventArgs;
use Shopware\Components\CacheManager;

/**
 * Clears the affected caches after the plugin configuration was saved in
 * the backend, so changes take effect without clearing caches manually
 * (Shopware 5 plugin guidelines, "Clearing the cache when configuration
 * changes").
 *
 * - config:   Shopware caches the plugin configuration.
 * - http:     security headers are stored with responses in the HTTP cache.
 * - template: the JavaScript escaping module acts on compiled templates.
 */
final class ConfigCacheSubscriber implements SubscriberInterface
{
    private const CACHE_TAGS = [CacheManager::CACHE_TAG_CONFIG, CacheManager::CACHE_TAG_HTTP, CacheManager::CACHE_TAG_TEMPLATE];

    /**
     * @readonly
     */
    private string $pluginName;

    /**
     * @readonly
     */
    private CacheManager $cacheManager;

    public function __construct(string $pluginName, CacheManager $cacheManager)
    {
        $this->pluginName = $pluginName;
        $this->cacheManager = $cacheManager;
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Config' => 'onPostDispatchConfig',
        ];
    }

    public function onPostDispatchConfig(Enlight_Controller_ActionEventArgs $args): void
    {
        $request = $args->getRequest();
        if (!$request->isPost()
            || strtolower((string) $request->getActionName()) !== 'saveform'
            || $request->getParam('name') !== $this->pluginName) {
            return;
        }

        $controller = $args->getSubject();
        // Shopware 5.6 declares View() as nullable.
        $view = $controller instanceof Enlight_Controller_Action ? $controller->View() : null;
        if ($view === null || $view->getAssign('success') !== true) {
            return;
        }

        $this->cacheManager->clearByTags(self::CACHE_TAGS);
    }
}
