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

/*
 * Creates a customer account in the default shop through Shopware's own
 * registration service, for the customer session checks.
 *
 * Usage: php Tests/Integration/customer.php <shopware-root> <email> <password>
 */

use Shopware\Models\Country\Country;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Shop\Shop as ShopModel;

if ($argc !== 4 || !is_file($argv[1] . '/autoload.php')) {
    fwrite(STDERR, "Usage: php customer.php <shopware-root> <email> <password>\n");
    exit(2);
}

$shopwareRoot = realpath($argv[1]);
chdir($shopwareRoot);
require $shopwareRoot . '/autoload.php';

$kernel = new Shopware\Kernel('production', false);
$kernel->boot();
$container = $kernel->getContainer();

// The registration service reads the current shop from the container, as in a storefront request.
$shopModel = $container->get('models')->getRepository(ShopModel::class)->getActiveDefault();
$container->get('shopware.components.shop_registration_service')->registerResources($shopModel);
$shop = $container->get('shopware_storefront.context_service')->createShopContext($shopModel->getId())->getShop();
$country = $container->get('models')->getRepository(Country::class)->findOneBy(['iso' => 'DE']);
if (!$country instanceof Country) {
    fwrite(STDERR, "FAIL: country DE not found.\n");
    exit(1);
}

$customer = new Customer();
$customer->setEmail($argv[2]);
$customer->setPassword($argv[3]);
$customer->setSalutation('mr');
$customer->setFirstname('Integration');
$customer->setLastname('Test');
$customer->setAccountMode(Customer::ACCOUNT_MODE_CUSTOMER);

$billing = new Address();
$billing->setSalutation('mr');
$billing->setFirstname('Integration');
$billing->setLastname('Test');
$billing->setStreet('Teststrasse 1');
$billing->setZipcode('12345');
$billing->setCity('Teststadt');
$billing->setCountry($country);

$container->get('shopware_account.register_service')->register($shop, $customer, $billing, null);

echo "  ok: test customer created\n";
