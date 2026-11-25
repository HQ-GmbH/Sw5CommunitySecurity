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
use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Psr\Log\LoggerInterface;
use Sw5CommunitySecurity\Components\Config\PluginConfig;
use Sw5CommunitySecurity\Components\Config\PluginConfigProvider;
use Sw5CommunitySecurity\Components\CustomerSession\SingleLogin;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Throwable;

/**
 * Module "Customer sessions" (F13).
 *
 * - Single login: sAdmin::loginUser() fires "Login_Successful" right before
 *   it stores the customer in the session; sAdmin::sCheckUser() runs on every
 *   request of a logged-in customer and lets "FilterGetUser" veto the
 *   account. An empty result logs the session out (sAdmin, 5.6.10/5.7.20).
 * - SameSite: the frontend session cookie is created when the "session"
 *   service is initialised. PHP's "session.cookie_samesite" is set just
 *   before; a value from the "session" section of config.php (Shopware 5.7)
 *   is applied later and therefore takes precedence.
 */
final class CustomerSessionSubscriber implements SubscriberInterface
{
    private const SAME_SITE_VALUES = [
        PluginConfig::SAME_SITE_LAX => 'Lax',
        PluginConfig::SAME_SITE_STRICT => 'Strict',
    ];

    /**
     * @readonly
     */
    private PluginConfigProvider $config;

    /**
     * @readonly
     */
    private SingleLogin $singleLogin;

    /**
     * @readonly
     */
    private ContainerInterface $container;

    /**
     * @readonly
     */
    private LoggerInterface $logger;

    public function __construct(PluginConfigProvider $config, SingleLogin $singleLogin, ContainerInterface $container, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->singleLogin = $singleLogin;
        $this->container = $container;
        $this->logger = $logger;
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Shopware_Modules_Admin_Login_Successful' => 'onLoginSuccessful',
            'Shopware_Modules_Admin_CheckUser_FilterGetUser' => 'onFilterGetUser',
            'Enlight_Bootstrap_InitResource_session' => 'onInitSession',
        ];
    }

    public function onLoginSuccessful(Enlight_Event_EventArgs $args): void
    {
        try {
            if (!$this->config->getForDefaultShop()->customerSessionSingleLogin) {
                return;
            }

            $customerId = self::customerId($args->get('user'));
            $session = $this->container->get('session');
            if ($customerId === null || !$session instanceof ArrayAccess) {
                return;
            }

            $session->offsetSet(SingleLogin::SESSION_KEY, $this->singleLogin->register($customerId));
        } catch (Throwable $exception) {
            // The login itself must not fail because of this module.
            $this->logger->error('Registering a customer login failed.', ['exception' => \get_class($exception)]);
        }
    }

    /**
     * @return mixed the customer row, or an empty array to log out an older session
     */
    public function onFilterGetUser(Enlight_Event_EventArgs $args)
    {
        $customer = $args->getReturn();

        try {
            $customerId = self::customerId($customer);
            $session = $args->get('session');
            if ($customerId === null
                || !$session instanceof ArrayAccess
                || !$this->config->getForDefaultShop()->customerSessionSingleLogin) {
                return $customer;
            }

            $token = $session->offsetExists(SingleLogin::SESSION_KEY) ? $session->offsetGet(SingleLogin::SESSION_KEY) : null;
            if ($this->singleLogin->isCurrent($customerId, $token)) {
                return $customer;
            }

            $session->offsetUnset(SingleLogin::SESSION_KEY);

            return [];
        } catch (Throwable $exception) {
            // Fail open: a database problem must not log out every customer.
            $this->logger->error('Checking a customer login failed.', ['exception' => \get_class($exception)]);

            return $customer;
        }
    }

    public function onInitSession(): void
    {
        try {
            $sameSite = self::SAME_SITE_VALUES[$this->config->getForCurrentShop()->customerSessionCookieSameSite] ?? null;
        } catch (Throwable $exception) {
            return;
        }

        if ($sameSite !== null && session_status() !== \PHP_SESSION_ACTIVE) {
            ini_set('session.cookie_samesite', $sameSite);
        }
    }

    /**
     * @param mixed $customer
     */
    private static function customerId($customer): ?int
    {
        if (!\is_array($customer) || !isset($customer['id']) || !is_numeric($customer['id'])) {
            return null;
        }

        $id = (int) $customer['id'];

        return $id > 0 ? $id : null;
    }
}
