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

use ArrayAccess;
use DateTimeImmutable;
use Enlight\Event\SubscriberInterface;
use Enlight_Components_Db_Adapter_Pdo_Mysql;
use Enlight_Controller_ActionEventArgs;
use Enlight_Controller_Request_Request;
use Enlight_Event_EventArgs;
use Psr\Log\LoggerInterface;
use Shopware_Components_Auth;
use Sw5CommunitySecurity\Components\Compat\ActionEvent;
use Sw5CommunitySecurity\Components\Config\DefaultShopConfigSource;
use Sw5CommunitySecurity\Components\Messages\MessageFormatter;
use Sw5CommunitySecurity\Components\PasswordPolicy\PasswordPolicy;
use Sw5CommunitySecurity\Components\TwoFactor\TwoFactorLogin;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Throwable;

/**
 * Module "Backend accounts" (F14).
 *
 * - Two-factor login: Shopware_Controllers_Backend_Login::loginAction()
 *   authenticates with user name and password and stores the identity in
 *   the backend session. For users with a second factor, the code from the
 *   login form is checked afterwards; without a valid code the identity is
 *   removed again and the login answers like a failed one.
 * - The second factor is a state of the backend session, not only a step of
 *   the login action: a valid code marks the session. Before every backend
 *   request the identity of an enrolled user is removed if the session is
 *   not marked, whichever way the identity got there (fail closed).
 * - The login form gets a code field (template block
 *   "backend/login/view/main/form").
 * - Password rules: "updateUser" of the user administration receives the
 *   new password in plain text; the action is skipped if it breaks a rule.
 */
final class BackendAccountSubscriber implements SubscriberInterface
{
    public const CODE_FIELD = 'sw5csTwoFactorCode';

    /**
     * Backend session key: ID of the user whose second factor was checked
     * (or who has none) in this session.
     */
    public const SESSION_VERIFIED = 'sw5csTwoFactorVerified';

    /**
     * @readonly
     */
    private DefaultShopConfigSource $config;

    /**
     * @readonly
     */
    private TwoFactorLogin $twoFactor;

    /**
     * @readonly
     */
    private MessageFormatter $messages;

    /**
     * @readonly
     */
    private ContainerInterface $container;

    /**
     * @readonly
     */
    private string $viewsDirectory;

    /**
     * @readonly
     */
    private LoggerInterface $logger;

    public function __construct(DefaultShopConfigSource $config, TwoFactorLogin $twoFactor, MessageFormatter $messages, ContainerInterface $container, string $pluginDirectory, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->twoFactor = $twoFactor;
        $this->messages = $messages;
        $this->container = $container;
        $this->viewsDirectory = rtrim($pluginDirectory, '/') . '/Resources/views/';
        $this->logger = $logger;
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Enlight_Controller_Front_PreDispatch' => 'onFrontPreDispatch',
            'Enlight_Controller_Action_PostDispatch_Backend_Login' => 'onPostDispatchLogin',
            'Enlight_Controller_Action_Backend_UserManager_updateUser' => 'onUpdateUser',
        ];
    }

    /**
     * Runs before the controller (and before the core Auth plugin) of every
     * backend request: removes the identity of an enrolled user whose second
     * factor was not checked in this session.
     */
    public function onFrontPreDispatch(Enlight_Event_EventArgs $args): void
    {
        $request = $args->get('request');
        if (!$request instanceof Enlight_Controller_Request_Request || strtolower((string) $request->getModuleName()) !== 'backend') {
            return;
        }

        if (!$this->config->getForDefaultShop()->backendTwoFactorEnabled) {
            return;
        }

        $session = $this->session();
        if ($session !== null && strtolower((string) $request->getControllerName()) === 'login'
            && str_replace('_', '', strtolower((string) $request->getActionName())) === 'login') {
            // A new login has to prove the second factor again, even in a
            // session in which the same user had done so before.
            $session->offsetUnset(self::SESSION_VERIFIED);
        }

        $auth = $this->container->get('auth');
        if (!$auth instanceof Shopware_Components_Auth || !$auth->hasIdentity()) {
            return;
        }

        $userId = $this->identityId($auth);
        if ($userId > 0 && $session !== null && $session->offsetExists(self::SESSION_VERIFIED) && $session->offsetGet(self::SESSION_VERIFIED) === $userId) {
            return;
        }

        try {
            $enrolled = $userId <= 0 || $this->twoFactor->isEnrolled($userId);
        } catch (Throwable $exception) {
            // Fail closed, like the check at login.
            $this->logger->error('Checking the two-factor state of a backend session failed.', ['exception' => \get_class($exception)]);
            $enrolled = true;
        }

        if (!$enrolled) {
            if ($session !== null) {
                $session->offsetSet(self::SESSION_VERIFIED, $userId);
            }

            return;
        }

        $auth->clearIdentity();
        $this->logger->warning('Backend session without checked second factor was logged out.');
    }

    public function onPostDispatchLogin(Enlight_Controller_ActionEventArgs $args): void
    {
        if (!$this->config->getForDefaultShop()->backendTwoFactorEnabled) {
            return;
        }

        $request = $args->getRequest();
        // Shopware removes "_" from action names when dispatching
        // (Dispatcher/Default.php, formatActionName), "lo_gin" runs loginAction().
        $action = str_replace('_', '', strtolower((string) $request->getActionName()));
        $view = $args->getSubject()->View();
        if ($view === null) {
            return;
        }

        if ($action === 'index' || $action === 'load') {
            $view->addTemplateDir($this->viewsDirectory);
            $view->extendsTemplate('backend/sw5cs_two_factor/login/view/main/form.js');

            return;
        }

        if ($action !== 'login' || $view->getAssign('success') !== true) {
            return;
        }

        $auth = $this->container->get('auth');
        if (!$auth instanceof Shopware_Components_Auth) {
            return;
        }

        $userId = $this->identityId($auth);
        if ($userId <= 0) {
            return;
        }

        try {
            [$result, $lockSeconds] = $this->twoFactor->verify($userId, $request->getParam(self::CODE_FIELD), time());
        } catch (Throwable $exception) {
            // Fail closed: without a working check, enrolled users cannot log in.
            $this->logger->error('Checking a two-factor code failed.', ['exception' => \get_class($exception)]);
            $result = TwoFactorLogin::RESULT_INVALID;
            $lockSeconds = 0;
        }

        $session = $this->session();
        if ($result !== TwoFactorLogin::RESULT_INVALID) {
            if ($session !== null) {
                $session->offsetSet(self::SESSION_VERIFIED, $userId);
            }

            return;
        }

        if ($session !== null) {
            $session->offsetUnset(self::SESSION_VERIFIED);
        }
        $auth->clearIdentity();
        $lockedUntil = null;
        if ($lockSeconds > 0) {
            $lockedUntil = $this->lock($userId, $lockSeconds);
            $this->logger->warning('Backend account locked after repeated wrong two-factor codes.', ['seconds' => $lockSeconds]);
        }

        $view->assign([
            'success' => false,
            'user' => null,
            'lockedUntil' => $lockedUntil,
            'sw5csTwoFactorRequired' => true,
        ]);
    }

    /**
     * @return bool|null true skips the action (notifyUntil)
     */
    public function onUpdateUser(Enlight_Event_EventArgs $args): ?bool
    {
        $request = ActionEvent::request($args);
        $config = $this->config->getForDefaultShop();
        if ($request === null || !$config->backendPasswordPolicyEnabled) {
            return null;
        }

        $password = $request->getParam('password');
        if (!\is_string($password) || $password === '') {
            return null;
        }

        $username = $request->getParam('username');
        $violations = PasswordPolicy::violations($password, \is_string($username) ? $username : '', $config->backendPasswordMinLength);
        if ($violations === []) {
            return null;
        }

        $rules = [];
        foreach ($violations as $rule) {
            $rules[] = $this->messages->format('password_rule_' . $rule, PasswordPolicy::MESSAGES_BY_RULE[$rule], ['length' => (string) $config->backendPasswordMinLength]);
        }

        $controller = ActionEvent::controller($args);
        $view = $controller !== null ? $controller->View() : null;
        if ($view !== null) {
            $view->assign([
                'success' => false,
                'message' => $this->messages->formatWithEscapedValues('password_rejected', PasswordPolicy::REJECTED_MESSAGE, ['rules' => implode('; ', $rules)]),
            ]);
        }

        return true;
    }

    private function identityId(Shopware_Components_Auth $auth): int
    {
        $identity = $auth->getIdentity();

        return \is_object($identity) && isset($identity->id) && is_numeric($identity->id) ? (int) $identity->id : 0;
    }

    /**
     * @return ArrayAccess<string, mixed>|null
     */
    private function session(): ?ArrayAccess
    {
        // Enlight_Components_Session_Namespace: Zend-based in 5.6, Symfony-based in 5.7.
        $session = $this->container->get('backendsession');

        return $session instanceof ArrayAccess ? $session : null;
    }

    /**
     * Also sets Shopware's own lock (s_core_auth.lockeduntil), so the login
     * form shows the lock. The binding lock is the plugin's own
     * (TwoFactorLogin), which Shopware cannot shorten.
     */
    private function lock(int $userId, int $seconds): string
    {
        $lockedUntil = new DateTimeImmutable('+' . $seconds . ' seconds');
        $db = $this->container->get('db');
        if ($db instanceof Enlight_Components_Db_Adapter_Pdo_Mysql) {
            // Database time, as the login compares "lockeduntil <= NOW()".
            $db->query('UPDATE `s_core_auth` SET `lockeduntil` = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE `id` = ?', [$seconds, $userId]);
        }

        return $lockedUntil->format(DateTimeImmutable::ATOM);
    }
}
