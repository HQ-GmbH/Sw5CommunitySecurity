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
use Enlight_Controller_EventArgs;
use Enlight_Controller_Request_RequestHttp;
use Psr\Log\LoggerInterface;
use Sw5CommunitySecurity\Components\ApiAccess\ApiAccessPolicy;
use Sw5CommunitySecurity\Components\Config\DefaultShopConfigSource;

/**
 * Module "Backend accounts", REST API: restricts the API to configured
 * client addresses (plugin setting "apiAllowedAddresses", empty = off).
 *
 * The core authenticates API requests in Enlight_Controller_Front_PreDispatch
 * (engine/Shopware/Plugins/Default/Core/RestApi/Bootstrap.php,
 * onFrontPreDispatch) and routes failures to the API error controller
 * (Controllers/Api/Error.php, noAuthAction). Requests from other addresses are
 * routed there as well, with status 403, whatever their credentials. The
 * client address is Request::getClientIp(), which honours "trustedproxies".
 */
final class ApiAccessSubscriber implements SubscriberInterface
{
    private const HTTP_FORBIDDEN = 403;

    /**
     * @readonly
     */
    private DefaultShopConfigSource $config;

    /**
     * @readonly
     */
    private LoggerInterface $logger;

    public function __construct(DefaultShopConfigSource $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Enlight_Controller_Front_PreDispatch' => 'onPreDispatch',
        ];
    }

    public function onPreDispatch(Enlight_Controller_EventArgs $args): void
    {
        $request = $args->getRequest();
        if ($request->getModuleName() !== 'api') {
            return;
        }

        $policy = new ApiAccessPolicy($this->config->getForDefaultShop()->apiAllowedAddresses);
        $clientAddress = $request instanceof Enlight_Controller_Request_RequestHttp ? $request->getClientIp() : null;
        if ($policy->allows($clientAddress)) {
            return;
        }

        $request->setControllerName('error');
        $request->setActionName('noauth');
        $args->getResponse()->setStatusCode(self::HTTP_FORBIDDEN);

        // No address in the log (see README, logging).
        $this->logger->warning('REST API request from a client address that is not allowed was rejected.');
    }
}
