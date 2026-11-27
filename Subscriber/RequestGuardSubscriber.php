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
use Sw5CommunitySecurity\Components\Config\DefaultShopConfigSource;
use Sw5CommunitySecurity\Components\RequestGuard\RequestInspector;

/**
 * Module "Request hardening": clears values with Smarty constructs of known
 * Smarty vulnerabilities from storefront requests (see RequestInspector).
 *
 * Runs at Enlight_Controller_Front_RouteShutdown like Shopware's input filter
 * (engine/Shopware/Plugins/Default/Frontend/InputFilter/Bootstrap.php,
 * onRouteShutdown, position -100) and after it, and cleans the same sources:
 * query, post, cookies, $_REQUEST, $_SERVER (request headers, address) and
 * route parameters. Backend and REST API are not touched: there, templates
 * are edited on purpose (template guard).
 */
final class RequestGuardSubscriber implements SubscriberInterface
{
    private const STOREFRONT_MODULES = ['frontend', 'widgets', ''];

    /**
     * @readonly
     */
    private DefaultShopConfigSource $config;

    /**
     * @readonly
     */
    private RequestInspector $inspector;

    /**
     * @readonly
     */
    private LoggerInterface $logger;

    public function __construct(DefaultShopConfigSource $config, RequestInspector $inspector, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->inspector = $inspector;
        $this->logger = $logger;
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Enlight_Controller_Front_RouteShutdown' => 'onRouteShutdown',
        ];
    }

    public function onRouteShutdown(Enlight_Controller_EventArgs $args): void
    {
        $request = $args->getRequest();
        if (!$request instanceof Enlight_Controller_Request_RequestHttp
            || !\in_array(strtolower((string) $request->getModuleName()), self::STOREFRONT_MODULES, true)
            || !$this->config->getForDefaultShop()->requestGuardEnabled) {
            return;
        }

        $removed = [];

        [$query, $found] = $this->inspector->clean($request->query->all(), 'query');
        if ($found !== []) {
            $request->query->replace($query);
            $_GET = $query;
            $removed += $found;
        }

        [$post, $found] = $this->inspector->clean($request->request->all(), 'post');
        if ($found !== []) {
            $request->request->replace($post);
            $_POST = $post;
            $removed += $found;
        }

        [$cookies, $found] = $this->inspector->clean($request->cookies->all(), 'cookie');
        if ($found !== []) {
            $request->cookies->replace($cookies);
            $_COOKIE = $cookies;
            $removed += $found;
        }

        // $_REQUEST mirrors query, post and cookies (request_order) and is what
        // templates read as $smarty.request; Shopware's input filter cleans it
        // too. Not logged separately, its values are already logged above.
        if ($removed !== []) {
            [$requestValues] = $this->inspector->clean($_REQUEST, 'request');
            $_REQUEST = $requestValues;
        }

        // Request headers and the raw address end up in $_SERVER, which
        // templates read as $smarty.server and Enlight's getHeader()/getServer()
        // read from the server bag. Shopware's input filter clears matching
        // values there as well (Plugins/Default/Frontend/InputFilter/Bootstrap.php).
        $server = $request->server->all();
        [$cleanServer, $found] = $this->inspector->clean($server, 'server');
        if ($found !== []) {
            $request->server->replace($cleanServer);
            foreach ($server as $name => $value) {
                if (!\is_string($name) || $value === null || ($cleanServer[$name] ?? null) !== null) {
                    continue;
                }
                if (strpos($name, 'HTTP_') === 0) {
                    $request->headers->remove(strtr(strtolower((string) substr($name, 5)), '_', '-'));
                } elseif (strpos($name, 'CONTENT_') === 0) {
                    $request->headers->remove(strtr(strtolower($name), '_', '-'));
                }
            }
            $removed += $found;
        }
        [$serverGlobal] = $this->inspector->clean($_SERVER, 'server');
        $_SERVER = $serverGlobal;

        $userParams = $request->getUserParams();
        [$params, $found] = $this->inspector->clean($userParams, 'param');
        if ($found !== []) {
            // setParams() only sets the given keys; removed keys are unset
            // one by one (setParam() with null deletes the entry).
            foreach (array_keys(array_diff_key($userParams, $params)) as $key) {
                $request->setParam((string) $key, null);
            }
            $request->setParams($params);
            $removed += $found;
        }

        if ($removed === []) {
            return;
        }

        // Only route, parameter names and rules: never values, user names or addresses.
        $this->logger->warning('Request hardening removed values with Smarty constructs.', [
            'source' => self::routePart($request->getModuleName()) . '/' . self::routePart($request->getControllerName()) . '/' . self::routePart($request->getActionName()),
            'rules' => $removed,
        ]);
    }

    /**
     * Controller and action names come from the URL; anything other than a
     * plain name is not logged, it could be the removed value itself.
     *
     * @param mixed $name
     */
    private static function routePart($name): string
    {
        return \is_string($name) && preg_match('/^[A-Za-z0-9_]{1,64}$/D', $name) === 1 ? $name : '[other]';
    }
}
