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
use Enlight_Controller_ActionEventArgs;
use Enlight_Event_EventArgs;
use Psr\Log\LoggerInterface;
use Sw5CommunitySecurity\Components\Compat\ActionEvent;
use Sw5CommunitySecurity\Components\Config\PluginConfigProvider;
use Sw5CommunitySecurity\Components\ContentGuard\ContentGuard;

/**
 * Module "Content check", backend layer.
 *
 * Translations: the backend saves
 * them with plain SQL (Shopware_Components_Translation), not via Doctrine.
 * Every translated value is inspected; plain text never contains markup and
 * therefore passes.
 *
 * Returning true from "Enlight_Controller_Action_Backend_Translation_saveTranslation"
 * makes Shopware skip the action (notifyUntil): nothing is saved and the
 * backend shows the message.
 *
 * REST API: translations sent to /api/translations, and the "translations"
 * part of products, variants and categories, are written with plain SQL as
 * well (Shopware\Components\Api\Resource\Translation, Article, Category).
 * Listeners on "Enlight_Controller_Action_Api_<Controller>_<action>" reject
 * them before the action with status 400 and the message.
 *
 * Shop pages: the shop pages module does not show error messages of
 * Site/saveSite; an Ext JS extension shows the content check's rejection.
 */
final class ContentGuardSubscriber implements SubscriberInterface
{
    /**
     * API controllers whose data contains translations, and the actions that write.
     */
    public const API_TRANSLATED_ENTITIES = ['Articles', 'Variants', 'Categories'];

    public const API_WRITE_ACTIONS = ['post', 'put', 'batch'];

    private const HTTP_BAD_REQUEST = 400;

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

    /**
     * @readonly
     */
    private string $viewsDirectory;

    public function __construct(PluginConfigProvider $configProvider, ContentGuard $guard, LoggerInterface $logger, string $pluginDirectory)
    {
        $this->configProvider = $configProvider;
        $this->guard = $guard;
        $this->logger = $logger;
        $this->viewsDirectory = rtrim($pluginDirectory, '/') . '/Resources/views/';
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        $events = [
            'Enlight_Controller_Action_Backend_Translation_saveTranslation' => 'onSaveTranslation',
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Site' => 'onPostDispatchSite',
        ];

        foreach (self::API_WRITE_ACTIONS as $action) {
            $events['Enlight_Controller_Action_Api_Translations_' . $action] = 'onApiTranslations';
            foreach (self::API_TRANSLATED_ENTITIES as $controller) {
                $events['Enlight_Controller_Action_Api_' . $controller . '_' . $action] = 'onApiEntityTranslations';
            }
        }

        return $events;
    }

    /**
     * /api/translations: every value sent is inspected (plain text passes).
     */
    public function onApiTranslations(Enlight_Event_EventArgs $args): ?bool
    {
        $request = ActionEvent::request($args);
        $post = $request !== null ? $request->getPost() : null;

        return \is_array($post) ? $this->rejectApi($args, $post, 'api:translations') : null;
    }

    /**
     * Products, variants, categories: only the "translations" part, which
     * bypasses Doctrine; the entity fields themselves are checked there.
     */
    public function onApiEntityTranslations(Enlight_Event_EventArgs $args): ?bool
    {
        $request = ActionEvent::request($args);
        if ($request === null) {
            return null;
        }

        $translations = [];
        self::collectTranslations($request->getPost(), '', 0, $translations);

        return $this->rejectApi($args, $translations, 'api:' . strtolower((string) $request->getControllerName()));
    }

    /**
     * @param mixed                $value
     * @param array<string, mixed> $translations path => translation data
     */
    public static function collectTranslations($value, string $path, int $depth, array &$translations): void
    {
        if (!\is_array($value) || $depth > ContentGuard::MAX_DEPTH) {
            return;
        }

        foreach ($value as $key => $child) {
            $childPath = $path === '' ? (string) $key : $path . '.' . $key;
            if ($key === 'translations') {
                $translations[$childPath] = $child;

                continue;
            }
            self::collectTranslations($child, $childPath, $depth + 1, $translations);
        }
    }

    public function onPostDispatchSite(Enlight_Controller_ActionEventArgs $args): void
    {
        $action = strtolower((string) $args->getRequest()->getActionName());
        $view = $args->getSubject()->View();
        if (($action !== 'index' && $action !== 'load') || $view === null) {
            return;
        }

        $view->addTemplateDir($this->viewsDirectory);
        $view->extendsTemplate('backend/sw5cs_content_guard/site/app.js');
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

    /**
     * @param array<array-key, mixed> $fields
     */
    private function rejectApi(Enlight_Event_EventArgs $args, array $fields, string $source): ?bool
    {
        if ($fields === [] || !$this->configProvider->getForCurrentShop()->contentGuardEnabled) {
            return null;
        }

        $violations = $this->guard->findViolations($fields);
        if ($violations === []) {
            return null;
        }

        // Only source, field names and rules: never content, user names or addresses.
        $this->logger->warning('Content check rejected editor content.', ['source' => $source, 'rules' => $violations]);

        $controller = ActionEvent::controller($args);
        if ($controller !== null) {
            $controller->Response()->setStatusCode(self::HTTP_BAD_REQUEST);
            // Shopware 5.6 declares View() as nullable.
            $view = $controller->View();
            if ($view !== null) {
                $view->assign(['success' => false, 'message' => $this->guard->buildMessage($violations)]);
            }
        }

        return true;
    }
}
