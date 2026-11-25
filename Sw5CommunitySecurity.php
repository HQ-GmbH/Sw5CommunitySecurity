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

namespace Sw5CommunitySecurity;

use Enlight_Components_Db_Adapter_Pdo_Mysql;
use Monolog\Logger;
use RuntimeException;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Models\User\Privilege;
use Shopware\Models\User\Resource;
use Shopware_Components_Acl;
use Sw5CommunitySecurity\Components\CustomerSession\DbLoginTokenStore;
use Sw5CommunitySecurity\DependencyInjection\TemplateFilterCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Plugin bootstrap.
 *
 * Services and event subscribers live in Resources/services.xml, the backend
 * configuration form in Resources/config.xml. Shopware synchronises both
 * automatically, so the lifecycle methods only take care of cache invalidation.
 */
class Sw5CommunitySecurity extends Plugin
{
    /**
     * ACL resource of the backend module "Security check"; the core Auth
     * plugin derives it from the controller name (lower case, without "_").
     */
    public const ACL_RESOURCE = 'sw5cssecuritycheck';

    /**
     * Privilege for restoring protection files from the backend module.
     */
    public const ACL_PRIVILEGE_RESTORE = 'restore';

    private const ACL_PRIVILEGES = ['read', self::ACL_PRIVILEGE_RESTORE];

    /**
     * Security headers end up in responses stored by the Shopware HTTP cache,
     * so the HTTP cache has to be cleared together with the default caches.
     */
    private const CACHES_TO_CLEAR = [
        InstallContext::CACHE_TAG_TEMPLATE,
        InstallContext::CACHE_TAG_CONFIG,
        InstallContext::CACHE_TAG_ROUTER,
        InstallContext::CACHE_TAG_PROXY,
        InstallContext::CACHE_TAG_HTTP,
    ];

    /**
     * Rejections are logged as warnings. Shopware's default log level in
     * production is "error", which would hide them, so the plugin log uses
     * "warning" unless the shop is configured more verbosely.
     */
    public function build(ContainerBuilder $container): void
    {
        $levelParameter = $this->getContainerPrefix() . '.logger.level';
        if (!$container->hasParameter($levelParameter)) {
            $shopwareLevel = $container->hasParameter('shopware.logger.level') ? $container->getParameter('shopware.logger.level') : null;
            $container->setParameter(
                $levelParameter,
                \is_int($shopwareLevel) && $shopwareLevel < Logger::WARNING ? $shopwareLevel : Logger::WARNING,
            );
        }

        $this->registerCompatibilityServices($container);
        $container->addCompilerPass(new TemplateFilterCompilerPass());

        parent::build($container);
    }

    public function install(InstallContext $context): void
    {
        $this->createAclResource();
        $this->db()->query(DbLoginTokenStore::CREATE_TABLE);
    }

    public function activate(ActivateContext $context): void
    {
        $context->scheduleClearCache(self::CACHES_TO_CLEAR);
    }

    public function deactivate(DeactivateContext $context): void
    {
        $context->scheduleClearCache(self::CACHES_TO_CLEAR);
    }

    public function update(UpdateContext $context): void
    {
        $this->createAclResource();
        $this->db()->query(DbLoginTokenStore::CREATE_TABLE);
        $context->scheduleClearCache(self::CACHES_TO_CLEAR);
    }

    /**
     * Data of its own: the ACL resource of the backend module and the table
     * of the "one login per customer account" option. Shopware removes the
     * configuration and the menu entry.
     * Caches are cleared only if the plugin was active, as
     * recommended by the Shopware 5 plugin guidelines.
     */
    public function uninstall(UninstallContext $context): void
    {
        if (!$context->keepUserData()) {
            $this->acl()->deleteResource(self::ACL_RESOURCE);
            $this->db()->query(DbLoginTokenStore::DROP_TABLE);
        }

        if ($context->getPlugin()->getActive()) {
            $context->scheduleClearCache(self::CACHES_TO_CLEAR);
        }
    }

    /**
     * Administrators are allowed everything; other backend roles need the
     * privilege "read" (view) or "restore" (restore protection files) on this
     * resource (user administration). The resource is not linked to the
     * plugin ID, so uninstall() removes it explicitly.
     */
    private function createAclResource(): void
    {
        $acl = $this->acl();
        if (!$acl->hasResourceInDatabase(self::ACL_RESOURCE)) {
            $acl->createResource(self::ACL_RESOURCE, self::ACL_PRIVILEGES);

            return;
        }

        // Updates from versions that only created the privilege "read".
        $models = $this->container->get('models');
        $resource = $models instanceof ModelManager ? $models->getRepository(Resource::class)->findOneBy(['name' => self::ACL_RESOURCE]) : null;
        if (!$resource instanceof Resource) {
            return;
        }

        $existing = [];
        foreach ($resource->getPrivileges() as $privilege) {
            if ($privilege instanceof Privilege) {
                $existing[] = $privilege->getName();
            }
        }

        foreach (array_diff(self::ACL_PRIVILEGES, $existing) as $name) {
            $acl->createPrivilege($resource->getId(), $name);
        }
    }

    private function db(): Enlight_Components_Db_Adapter_Pdo_Mysql
    {
        $db = $this->container->get('db');
        if (!$db instanceof Enlight_Components_Db_Adapter_Pdo_Mysql) {
            throw new RuntimeException('The Shopware database adapter is not available.');
        }

        return $db;
    }

    private function acl(): Shopware_Components_Acl
    {
        $acl = $this->container->get('acl');
        if (!$acl instanceof Shopware_Components_Acl) {
            throw new RuntimeException('The Shopware ACL service is not available.');
        }

        return $acl;
    }

    /**
     * Shopware 5.6 and 5.7 differ in the plugin configuration reader and the
     * Doctrine DBAL version. The matching adapters are registered here; class
     * names are strings, so no class of the other Shopware version is loaded.
     */
    private function registerCompatibilityServices(ContainerBuilder $container): void
    {
        $isShopware57 = class_exists('Shopware\\Components\\Plugin\\Configuration\\CachedReader');
        $compatNamespace = 'Sw5CommunitySecurity\\Components\\Compat\\' . ($isShopware57 ? 'Sw57' : 'Sw56') . '\\';

        $container->setDefinition(
            'Sw5CommunitySecurity\\Components\\Config\\ConfigReader',
            (new Definition($compatNamespace . 'PluginConfigReader', [
                new Reference($isShopware57 ? 'Shopware\\Components\\Plugin\\Configuration\\CachedReader' : 'shopware.plugin.cached_config_reader'),
            ]))->setPublic(false),
        );
        $container->setDefinition(
            'Sw5CommunitySecurity\\Components\\Database\\RowReader',
            (new Definition($compatNamespace . 'DbalRowReader', [new Reference('dbal_connection')]))->setPublic(false),
        );
    }
}
