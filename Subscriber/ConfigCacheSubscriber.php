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
use Enlight_Event_EventArgs;
use Psr\Log\LoggerInterface;
use Shopware\Components\CacheManager;
use Sw5CommunitySecurity\Components\Compat\ActionEvent;
use Sw5CommunitySecurity\Components\Config\PluginConfigProvider;
use Throwable;

/**
 * Clears the affected caches after the plugin configuration was saved in
 * the backend, so changes take effect without clearing caches manually
 * (Shopware 5 plugin guidelines, "Clearing the cache when configuration
 * changes").
 *
 * - config:   Shopware caches the plugin configuration.
 * - http:     security headers are stored with responses in the HTTP cache.
 * - template: the JavaScript escaping module acts on compiled templates.
 *
 * Switching off the two-factor module lifts the code check for every backend
 * user, so a successful save that does so is logged as a warning.
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

    /**
     * @readonly
     */
    private PluginConfigProvider $config;

    /**
     * @readonly
     */
    private LoggerInterface $logger;

    /**
     * Set before the save if it switches the two-factor module off.
     */
    private bool $twoFactorBeingDisabled = false;

    public function __construct(string $pluginName, CacheManager $cacheManager, PluginConfigProvider $config, LoggerInterface $logger)
    {
        $this->pluginName = $pluginName;
        $this->cacheManager = $cacheManager;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Enlight_Controller_Action_Backend_Config_saveForm' => 'onSaveForm',
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Config' => 'onPostDispatchConfig',
        ];
    }

    /**
     * Before the save: does it switch the two-factor module off? Returns
     * nothing, so the action always runs (notifyUntil).
     */
    public function onSaveForm(Enlight_Event_EventArgs $args): void
    {
        $this->twoFactorBeingDisabled = false;
        $request = ActionEvent::request($args);
        if ($request === null || $request->getParam('name') !== $this->pluginName) {
            return;
        }

        try {
            if (!$this->config->getForDefaultShop()->backendTwoFactorEnabled) {
                return;
            }
        } catch (Throwable $exception) {
            return;
        }

        $elements = $request->getParam('elements');
        foreach (\is_array($elements) ? $elements : [] as $element) {
            if (!\is_array($element) || ($element['name'] ?? null) !== 'backendTwoFactorEnabled') {
                continue;
            }
            foreach (\is_array($element['values'] ?? null) ? $element['values'] : [] as $value) {
                $stored = \is_array($value) ? ($value['value'] ?? null) : null;
                if (\in_array($stored, [false, 0, '0', 'false', ''], true)) {
                    $this->twoFactorBeingDisabled = true;
                }
            }
        }
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

        if ($this->twoFactorBeingDisabled) {
            $this->twoFactorBeingDisabled = false;
            // No user name: the log names place and setting only.
            $this->logger->warning('The two-factor login module was switched off in the plugin configuration; the login no longer asks for codes.');
        }
    }
}
