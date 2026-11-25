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

use Psr\Log\LoggerInterface;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Components\Random;
use Sw5CommunitySecurity\Components\ProtectionFiles\ProtectionFiles;
use Sw5CommunitySecurity\Components\SecurityCheck\SecurityCheckRunner;
use Sw5CommunitySecurity\Sw5CommunitySecurity;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lightweight backend module (plain HTML in an iframe, see the Shopware 5
 * developer guide "Lightweight backend modules"). Login is enforced by the
 * core Auth plugin for every backend controller; access additionally
 * requires the ACL resource "sw5cssecuritycheck" (privilege "read"), which
 * only administrators have unless granted to other roles. Restoring
 * protection files requires the privilege "restore".
 */
class Shopware_Controllers_Backend_Sw5csSecurityCheck extends Enlight_Controller_Action implements CSRFWhitelistAware
{
    private const RESULTS = ['done', 'failed', 'nothing', 'denied'];

    /**
     * Session key of the backend CSRF token; the core accepts the token from
     * the form parameter "__csrf_token" (CSRFTokenValidator in 5.6.10 and
     * 5.7.20; 5.6 has no public constants for either name).
     */
    private const CSRF_SESSION_KEY = 'X-CSRF-Token';

    /**
     * The iframe is loaded with a plain GET request without CSRF token. The
     * action only reads and changes nothing. restoreProtectionFiles is not
     * whitelisted: the form sends the token of the backend session.
     *
     * @return list<string>
     */
    public function getWhitelistedCSRFActions()
    {
        return ['index'];
    }

    /**
     * Shopware registers a plugin's Resources/views only if the plugin
     * enables $autoloadViews, which would add it to every backend controller
     * and the theme inheritance. Only this controller needs it.
     */
    public function preDispatch(): void
    {
        $view = $this->View();
        if ($view !== null) {
            $view->addTemplateDir(__DIR__ . '/../../Resources/views/');
        }
    }

    public function indexAction(): void
    {
        $view = $this->View();
        if ($view === null) {
            return;
        }

        $runner = $this->get(SecurityCheckRunner::class);
        if (!$runner instanceof SecurityCheckRunner) {
            return;
        }

        $request = $this->Request();
        $rows = $runner->run($request instanceof Request ? $request : null);

        $missingProtectionFiles = false;
        foreach ($rows as $row) {
            if ($row['id'] === 'protection_files' && $row['status'] === 'warning') {
                $missingProtectionFiles = true;
            }
        }

        // Enlight_Components_Session_Namespace: Zend-based in 5.6, Symfony-based in 5.7.
        $session = $this->get('backendsession');
        $csrfToken = $session instanceof ArrayAccess ? $session->offsetGet(self::CSRF_SESSION_KEY) : null;
        if ($session instanceof ArrayAccess && (!\is_string($csrfToken) || $csrfToken === '')) {
            // Created like CSRFToken::generateAction() if the backend has not requested one yet.
            $csrfToken = Random::getAlphanumericString(30);
            $session->offsetSet(self::CSRF_SESSION_KEY, $csrfToken);
        }
        $result = $request->getParam('result');

        $view->loadTemplate('backend/sw5cs_security_check/index.tpl');
        $view->assign('sw5csRows', $rows);
        $view->assign('sw5csLabels', $runner->labels($rows));
        $view->assign('sw5csShowRestore', $missingProtectionFiles && \is_string($csrfToken) && $this->mayRestore());
        $view->assign('sw5csCsrfToken', \is_string($csrfToken) ? $csrfToken : '');
        $view->assign('sw5csResult', \in_array($result, self::RESULTS, true) ? $result : '');
    }

    /**
     * POST only; the CSRF token is checked by the core before this action runs.
     */
    public function restoreProtectionFilesAction(): void
    {
        if (!$this->Request()->isPost()) {
            $this->redirect(['action' => 'index']);

            return;
        }

        if (!$this->mayRestore()) {
            $this->redirect(['action' => 'index', 'result' => 'denied']);

            return;
        }

        $protectionFiles = $this->get(ProtectionFiles::class);
        $root = $this->container->getParameter('kernel.root_dir');
        if (!$protectionFiles instanceof ProtectionFiles || !\is_string($root)) {
            $this->redirect(['action' => 'index', 'result' => 'failed']);

            return;
        }

        $restore = $protectionFiles->restore($root);
        $logger = $this->get('sw5_community_security.logger');
        if ($restore->restored !== [] && $logger instanceof LoggerInterface) {
            $logger->warning('Missing protection files were restored from the backend.', ['files' => $restore->restored]);
        }

        if ($restore->failed !== []) {
            $result = 'failed';
        } else {
            $result = $restore->restored === [] ? 'nothing' : 'done';
        }

        $this->redirect(['action' => 'index', 'result' => $result]);
    }

    private function mayRestore(): bool
    {
        $auth = $this->get('auth');
        $acl = $this->get('acl');
        if (!$auth instanceof Shopware_Components_Auth || !$acl instanceof Shopware_Components_Acl) {
            return false;
        }

        $identity = $auth->getIdentity();
        $role = \is_object($identity) && isset($identity->role) ? $identity->role : null;
        if (!$role instanceof Zend_Acl_Role_Interface) {
            return false;
        }

        try {
            return $acl->isAllowed($role, Sw5CommunitySecurity::ACL_RESOURCE, Sw5CommunitySecurity::ACL_PRIVILEGE_RESTORE);
        } catch (Zend_Acl_Exception $exception) {
            // Role or resource not registered in the ACL.
            return false;
        }
    }
}
