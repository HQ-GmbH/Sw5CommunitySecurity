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
use Enlight_Event_EventArgs;
use Psr\Log\LoggerInterface;
use Sw5CommunitySecurity\Components\Compat\ActionEvent;
use Sw5CommunitySecurity\Components\Config\PluginConfigProvider;
use Sw5CommunitySecurity\Components\ContentGuard\ContentGuard;

/**
 * Module "Content check", backend layer for translations: the backend saves
 * them with plain SQL (Shopware_Components_Translation), not via Doctrine.
 * Every translated value is inspected; plain text never contains markup and
 * therefore passes.
 *
 * Returning true from "Enlight_Controller_Action_Backend_Translation_saveTranslation"
 * makes Shopware skip the action (notifyUntil): nothing is saved and the
 * backend shows the message.
 */
final class ContentGuardSubscriber implements SubscriberInterface
{
    /**
     * @readonly
     */
    private PluginConfigProvider $configProvider;

    /**
     * @readonly
     */
    private ContentGuard $guard;

    /**
     * @readonly
     */
    private LoggerInterface $logger;

    public function __construct(PluginConfigProvider $configProvider, ContentGuard $guard, LoggerInterface $logger)
    {
        $this->configProvider = $configProvider;
        $this->guard = $guard;
        $this->logger = $logger;
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Enlight_Controller_Action_Backend_Translation_saveTranslation' => 'onSaveTranslation',
        ];
    }

    public function onSaveTranslation(Enlight_Event_EventArgs $args): ?bool
    {
        $request = ActionEvent::request($args);
        if ($request === null || !$this->configProvider->getForCurrentShop()->contentGuardEnabled) {
            return null;
        }

        $data = $request->getParam('data', []);
        $violations = $this->guard->findViolations(\is_array($data) ? $data : []);
        if ($violations === []) {
            return null;
        }

        $type = $request->getParam('type');
        // Only type, field names and rules: never content, user names or addresses.
        $this->logger->warning('Content check rejected editor content.', [
            'source' => 'backend:translation/' . (\is_string($type) ? $type : ''),
            'rules' => $violations,
        ]);

        $controller = ActionEvent::controller($args);
        // Shopware 5.6 declares View() as nullable.
        $view = $controller !== null ? $controller->View() : null;
        if ($view !== null) {
            $view->assign(['success' => false, 'message' => $this->guard->buildMessage($violations)]);
        }

        return true;
    }
}
