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
 * Integration check for the Doctrine fallback of the template guard: saving a
 * mail template or product export through the entity manager (as the REST API
 * or other plugins do) must be rejected when it contains disallowed Smarty
 * constructs. The markers used are harmless; they are never rendered.
 *
 * Usage: php Tests/Integration/doctrine-template-guard.php <shopware-root>
 */

use Shopware\Models\Config\Element as ConfigElement;
use Shopware\Models\Config\Value as ConfigValue;
use Shopware\Models\Document\Element as DocumentElement;
use Shopware\Models\Mail\Mail;
use Shopware\Models\Newsletter\Newsletter;
use Shopware\Models\Payment\Payment;
use Shopware\Models\ProductFeed\ProductFeed;
use Shopware\Models\Shop\Shop;
use Sw5CommunitySecurity\Components\TemplateGuard\UnsafeTemplateException;

if ($argc !== 2 || !is_file($argv[1] . '/autoload.php')) {
    fwrite(STDERR, "Usage: php doctrine-template-guard.php <shopware-root>\n");
    exit(2);
}

$shopwareRoot = realpath($argv[1]);
chdir($shopwareRoot);
require $shopwareRoot . '/autoload.php';

$kernel = new Shopware\Kernel('production', false);
$kernel->boot();
$container = $kernel->getContainer();

/**
 * Runs $change against a fresh entity manager state and reports whether the
 * flush was rejected by the template guard.
 */
function attempt(Closure $change): string
{
    global $container;

    $container->reset('models');
    $entityManager = $container->get('models');

    try {
        $change($entityManager);
        $entityManager->flush();

        return 'saved';
    } catch (Throwable $exception) {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof UnsafeTemplateException) {
                return 'rejected';
            }
        }

        return 'error: ' . get_class($exception) . ': ' . $exception->getMessage();
    }
}

$checks = [
    'mail with safe change is saved' => [
        'saved',
        static function ($entityManager): void {
            $mail = $entityManager->getRepository(Mail::class)->findOneBy(['name' => 'sORDER']);
            $mail->setSubject($mail->getSubject() . ' ');
        },
    ],
    'mail with {extends} is rejected' => [
        'rejected',
        static function ($entityManager): void {
            $mail = $entityManager->getRepository(Mail::class)->findOneBy(['name' => 'sORDER']);
            $mail->setContent('{extends file="sw5cs-integration-marker.tpl"}' . $mail->getContent());
        },
    ],
    'new product export with {fetch} is rejected' => [
        'rejected',
        static function ($entityManager): void {
            $feed = new ProductFeed();
            $feed->fromArray([
                'name' => 'sw5cs integration marker',
                'fileName' => 'sw5cs-marker.csv',
                'header' => 'id;name',
                'body' => '{fetch file="sw5cs-integration-marker.txt"}',
                'footer' => '',
            ]);
            $feed->setLastExport('now');
            $feed->setCacheRefreshed('2000-01-01');
            $entityManager->persist($feed);
        },
    ],
    'payment with safe change is saved' => [
        'saved',
        static function ($entityManager): void {
            $payment = $entityManager->getRepository(Payment::class)->findOneBy(['name' => 'invoice']);
            $payment->setAdditionalDescription($payment->getAdditionalDescription() . ' ');
        },
    ],
    'payment with {include} from variable is rejected (strict)' => [
        'rejected',
        static function ($entityManager): void {
            $payment = $entityManager->getRepository(Payment::class)->findOneBy(['name' => 'invoice']);
            $payment->setAdditionalDescription('{include file=$sw5csIntegrationMarker}');
        },
    ],
    'document box with {extends} is rejected' => [
        'rejected',
        static function ($entityManager): void {
            $box = $entityManager->getRepository(DocumentElement::class)->findOneBy(['name' => 'Body']);
            $box->setValue('{extends file="sw5cs-integration-marker.tpl"}');
        },
    ],
    'new newsletter with {fetch} in subject is rejected' => [
        'rejected',
        static function ($entityManager): void {
            $newsletter = new Newsletter();
            $newsletter->fromArray([
                'subject' => '{fetch file="sw5cs-integration-marker.txt"}',
                'senderMail' => 'sw5cs-marker@example.org',
                'senderName' => 'sw5cs marker',
                'groups' => '',
                'plaintext' => false,
                'templateId' => 1,
                'languageId' => 1,
                'status' => 0,
                'recipients' => 0,
                'read' => 0,
                'clicked' => 0,
                'customerGroup' => 'EK',
                'publish' => false,
            ]);
            $entityManager->persist($newsletter);
        },
    ],
    'SEO template setting with {fetch} is rejected' => [
        'rejected',
        static function ($entityManager): void {
            $value = new ConfigValue();
            $value->setElement($entityManager->getRepository(ConfigElement::class)->findOneBy(['name' => 'routerarticletemplate']));
            $value->setShop($entityManager->getReference(Shop::class, 1));
            $value->setValue('{fetch file="sw5cs-integration-marker.txt"}');
            $entityManager->persist($value);
        },
    ],
];

$failed = false;
foreach ($checks as $label => [$expected, $change]) {
    $actual = attempt($change);
    $ok = $actual === $expected;
    $failed = $failed || !$ok;
    printf("  %s: %s (expected %s, got %s)\n", $ok ? 'ok' : 'FAIL', $label, $expected, $actual);
}

exit($failed ? 1 : 0);
