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
use Sw5CommunitySecurity\Components\Config\PluginConfigProvider;
use Sw5CommunitySecurity\Components\Messages\MessageFormatter;
use Sw5CommunitySecurity\Components\TwoFactor\Totp;
use Sw5CommunitySecurity\Components\TwoFactor\TwoFactorLabels;
use Sw5CommunitySecurity\Components\TwoFactor\TwoFactorLogin;
use Sw5CommunitySecurity\Sw5CommunitySecurity;

/**
 * Lightweight backend module (plain HTML in an iframe) where every backend
 * user sets up the second factor for their own account. Without an ACL
 * resource for this controller, the core Auth plugin admits every logged-in
 * backend user. Removing the second factor of other users requires the
 * privilege "twofactorreset" on the resource "sw5cssecuritycheck".
 */
class Shopware_Controllers_Backend_Sw5csTwoFactor extends Enlight_Controller_Action implements CSRFWhitelistAware
{
    /**
     * Pending secret during set-up, kept in the backend session only.
     */
    private const SESSION_PENDING_SECRET = 'sw5csTwoFactorPendingSecret';

    private const CSRF_SESSION_KEY = 'X-CSRF-Token';

    private const ISSUER = 'Shopware Backend';

    private const RESULTS = ['enabled', 'disabled', 'invalid_code', 'reset', 'denied', 'cancelled'];

    /**
     * Only the page itself is loaded without CSRF token (GET in the iframe);
     * every changing action is POST with the token of the backend session.
     *
     * @return list<string>
     */
    public function getWhitelistedCSRFActions()
    {
        return ['index'];
    }

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
        $userId = $this->currentUserId();
        if ($view === null || $userId === null) {
            return;
        }

        $twoFactor = $this->twoFactor();
        $pending = $this->session()->offsetGet(self::SESSION_PENDING_SECRET);
        $pending = \is_string($pending) && !$twoFactor->isEnrolled($userId) ? $pending : null;
        $result = $this->Request()->getParam('result');

        $labels = [];
        $formatter = $this->get(MessageFormatter::class);
        foreach (TwoFactorLabels::LABELS as $key => $default) {
            $labels[$key] = $formatter instanceof MessageFormatter ? $formatter->formatText($key, $default) : $default;
        }

        $csrfToken = $this->session()->offsetGet(self::CSRF_SESSION_KEY);

        $view->loadTemplate('backend/sw5cs_two_factor/index.tpl');
        $view->assign([
            'sw5csLabels' => $labels,
            'sw5csModuleEnabled' => $this->moduleEnabled(),
            'sw5csEnrolled' => $twoFactor->isEnrolled($userId),
            'sw5csPendingSecret' => $pending !== null ? trim(chunk_split($pending, 4, ' ')) : '',
            'sw5csPendingUri' => $pending !== null ? Totp::uri($pending, self::ISSUER, $this->currentUsername()) : '',
            'sw5csCsrfToken' => \is_string($csrfToken) ? $csrfToken : '',
            'sw5csResult' => \in_array($result, self::RESULTS, true) ? $result : '',
            'sw5csUsers' => $this->mayReset() ? $this->enrolledUsers($twoFactor) : [],
        ]);
    }

    public function startAction(): void
    {
        if ($this->Request()->isPost() && $this->currentUserId() !== null) {
            $this->session()->offsetSet(self::SESSION_PENDING_SECRET, Totp::generateSecret());
        }

        $this->redirect(['action' => 'index']);
    }

    public function cancelAction(): void
    {
        if (!$this->Request()->isPost()) {
            $this->redirect(['action' => 'index']);

            return;
        }

        $this->session()->offsetUnset(self::SESSION_PENDING_SECRET);
        $this->redirect(['action' => 'index', 'result' => 'cancelled']);
    }

    public function confirmAction(): void
    {
        $userId = $this->currentUserId();
        $pending = $this->session()->offsetGet(self::SESSION_PENDING_SECRET);
        if (!$this->Request()->isPost() || $userId === null || !\is_string($pending)) {
            $this->redirect(['action' => 'index']);

            return;
        }

        if (!$this->twoFactor()->enroll($userId, $pending, $this->code(), time())) {
            $this->redirect(['action' => 'index', 'result' => 'invalid_code']);

            return;
        }

        $this->session()->offsetUnset(self::SESSION_PENDING_SECRET);
        $this->logger()->warning('Two-factor login was switched on for a backend user.');
        $this->redirect(['action' => 'index', 'result' => 'enabled']);
    }

    public function disableAction(): void
    {
        $userId = $this->currentUserId();
        if (!$this->Request()->isPost() || $userId === null) {
            $this->redirect(['action' => 'index']);

            return;
        }

        if (!$this->twoFactor()->disable($userId, $this->code(), time())) {
            $this->redirect(['action' => 'index', 'result' => 'invalid_code']);

            return;
        }

        $this->logger()->warning('Two-factor login was switched off by a backend user.');
        $this->redirect(['action' => 'index', 'result' => 'disabled']);
    }

    public function resetAction(): void
    {
        if (!$this->Request()->isPost()) {
            $this->redirect(['action' => 'index']);

            return;
        }

        if (!$this->mayReset()) {
            $this->redirect(['action' => 'index', 'result' => 'denied']);

            return;
        }

        $userId = $this->Request()->getParam('userId');
        if (is_numeric($userId) && (int) $userId > 0) {
            $this->twoFactor()->reset((int) $userId);
            $this->logger()->warning('Two-factor login of a backend user was removed by an administrator.');
        }

        $this->redirect(['action' => 'index', 'result' => 'reset']);
    }

    private function code(): string
    {
        $code = $this->Request()->getParam('code');

        return \is_string($code) ? $code : '';
    }

    private function moduleEnabled(): bool
    {
        $config = $this->get(PluginConfigProvider::class);

        return $config instanceof PluginConfigProvider && $config->getForDefaultShop()->backendTwoFactorEnabled;
    }

    private function currentUserId(): ?int
    {
        $identity = $this->identity();
        if ($identity === null || !isset($identity->id) || !is_numeric($identity->id)) {
            return null;
        }

        return (int) $identity->id;
    }

    private function currentUsername(): string
    {
        $identity = $this->identity();

        return $identity !== null && isset($identity->username) && \is_string($identity->username) ? $identity->username : 'backend';
    }

    private function identity(): ?object
    {
        $auth = $this->get('auth');
        $identity = $auth instanceof Shopware_Components_Auth ? $auth->getIdentity() : null;

        return \is_object($identity) ? $identity : null;
    }

    private function mayReset(): bool
    {
        $identity = $this->identity();
        $acl = $this->get('acl');
        $role = $identity !== null && isset($identity->role) ? $identity->role : null;
        if (!$acl instanceof Shopware_Components_Acl || !$role instanceof Zend_Acl_Role_Interface) {
            return false;
        }

        try {
            return $acl->isAllowed($role, Sw5CommunitySecurity::ACL_RESOURCE, Sw5CommunitySecurity::ACL_PRIVILEGE_TWO_FACTOR_RESET);
        } catch (Zend_Acl_Exception $exception) {
            return false;
        }
    }

    /**
     * @return list<array{id: int, username: string}>
     */
    private function enrolledUsers(TwoFactorLogin $twoFactor): array
    {
        $ids = $twoFactor->enrolledUserIds();
        $db = $this->get('db');
        if ($ids === [] || !$db instanceof Enlight_Components_Db_Adapter_Pdo_Mysql) {
            return [];
        }

        $users = [];
        $rows = $db->fetchPairs('SELECT `id`, `username` FROM `s_core_auth` WHERE `id` IN (' . implode(',', $ids) . ') ORDER BY `username`');
        foreach ($rows as $id => $username) {
            if (is_numeric($id) && \is_string($username)) {
                $users[] = ['id' => (int) $id, 'username' => $username];
            }
        }

        return $users;
    }

    private function twoFactor(): TwoFactorLogin
    {
        $twoFactor = $this->get(TwoFactorLogin::class);
        if (!$twoFactor instanceof TwoFactorLogin) {
            throw new RuntimeException('Two-factor service is not available.');
        }

        return $twoFactor;
    }

    /**
     * @return ArrayAccess<string, mixed>
     */
    private function session(): ArrayAccess
    {
        // Enlight_Components_Session_Namespace: Zend-based in 5.6, Symfony-based in 5.7.
        $session = $this->get('backendsession');
        if (!$session instanceof ArrayAccess) {
            throw new RuntimeException('Backend session is not available.');
        }

        return $session;
    }

    private function logger(): LoggerInterface
    {
        $logger = $this->get('sw5_community_security.logger');
        if (!$logger instanceof LoggerInterface) {
            throw new RuntimeException('Plugin logger is not available.');
        }

        return $logger;
    }
}
