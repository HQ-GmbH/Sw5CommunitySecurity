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
use Enlight_Controller_Response_ResponseHttp;
use Sw5CommunitySecurity\Components\Config\PluginConfigProvider;
use Sw5CommunitySecurity\Components\SecurityHeaders\HeaderPolicy;

/**
 * Module "HTTP security headers".
 *
 * Runs once per request after the dispatch loop, so the headers are part of
 * the response before Shopware's HTTP cache stores it.
 */
final class SecurityHeaderSubscriber implements SubscriberInterface
{
    public function __construct(
        private readonly PluginConfigProvider $configProvider,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Enlight_Controller_Front_DispatchLoopShutdown' => 'onDispatchLoopShutdown',
        ];
    }

    public function onDispatchLoopShutdown(Enlight_Controller_EventArgs $args): void
    {
        $request = $args->getRequest();
        $response = $args->getResponse();

        if (!$request instanceof Enlight_Controller_Request_RequestHttp
            || !$response instanceof Enlight_Controller_Response_ResponseHttp
        ) {
            return;
        }

        $headers = HeaderPolicy::build(
            $this->configProvider->getForCurrentShop(),
            (string) $request->getModuleName(),
            $request->isSecure()
        );

        foreach ($headers as $name => $value) {
            // Never override a value set by Shopware, the theme or another plugin.
            if ($response->headers->has($name)) {
                continue;
            }

            $response->headers->set($name, $value);
        }
    }
}
