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
use Enlight_Controller_Action;
use Enlight_Controller_ActionEventArgs;
use Enlight_Event_EventArgs;
use Shopware\Models\Mail\Mail;
use Shopware\Models\ProductFeed\ProductFeed;
use Sw5CommunitySecurity\Components\Config\PluginConfigProvider;
use Sw5CommunitySecurity\Components\TemplateGuard\TemplateGuard;

/**
 * Module "Template guard".
 *
 * Rejects Smarty templates with disallowed constructs before Shopware stores
 * or compiles them:
 *
 * - Backend actions (clean error message in the backend): saving mail
 *   templates, mail template translations and product exports, and the
 *   "check Smarty" preview of the mail module, which compiles without saving.
 * - Doctrine lifecycle events of the Mail and ProductFeed models as a second
 *   line of defence for every other write path (REST API, other plugins).
 */
final class TemplateGuardSubscriber implements SubscriberInterface
{
    private const MAIL_FIELDS = ['subject', 'content', 'contentHtml', 'fromMail', 'fromName'];
    private const PRODUCT_FEED_FIELDS = ['header', 'body', 'footer'];
    private const MAIL_TRANSLATION_TYPE = 'config_mails';

    public function __construct(
        private readonly PluginConfigProvider $configProvider,
        private readonly TemplateGuard $guard,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Enlight_Controller_Action_Backend_Mail_createMail' => 'onSaveMail',
            'Enlight_Controller_Action_Backend_Mail_updateMail' => 'onSaveMail',
            'Enlight_Controller_Action_Backend_Mail_verifySmarty' => 'onVerifySmarty',
            'Enlight_Controller_Action_Backend_ProductFeed_saveFeed' => 'onSaveProductFeed',
            'Enlight_Controller_Action_Backend_Translation_saveTranslation' => 'onSaveTranslation',
            Mail::class . '::prePersist' => 'onMailEntity',
            Mail::class . '::preUpdate' => 'onMailEntity',
            ProductFeed::class . '::prePersist' => 'onProductFeedEntity',
            ProductFeed::class . '::preUpdate' => 'onProductFeedEntity',
        ];
    }

    public function onSaveMail(Enlight_Controller_ActionEventArgs $args): ?bool
    {
        return $this->rejectRequest($args, $this->pick($args->getRequest()->getParams(), self::MAIL_FIELDS));
    }

    public function onVerifySmarty(Enlight_Controller_ActionEventArgs $args): ?bool
    {
        return $this->rejectRequest($args, ['value' => $args->getRequest()->getParam('value')]);
    }

    public function onSaveProductFeed(Enlight_Controller_ActionEventArgs $args): ?bool
    {
        return $this->rejectRequest($args, $this->pick($args->getRequest()->getParams(), self::PRODUCT_FEED_FIELDS));
    }

    public function onSaveTranslation(Enlight_Controller_ActionEventArgs $args): ?bool
    {
        $request = $args->getRequest();
        if ((string) $request->getParam('type') !== self::MAIL_TRANSLATION_TYPE) {
            return null;
        }

        $data = $request->getParam('data', []);

        return $this->rejectRequest($args, \is_array($data) ? $data : []);
    }

    public function onMailEntity(Enlight_Event_EventArgs $args): void
    {
        $mail = $args->get('entity');
        if (!$mail instanceof Mail || !$this->isEnabled()) {
            return;
        }

        $this->guard->assertSafe([
            'subject' => $mail->getSubject(),
            'content' => $mail->getContent(),
            'contentHtml' => $mail->getContentHtml(),
            'fromMail' => $mail->getFromMail(),
            'fromName' => $mail->getFromName(),
        ]);
    }

    public function onProductFeedEntity(Enlight_Event_EventArgs $args): void
    {
        $productFeed = $args->get('entity');
        if (!$productFeed instanceof ProductFeed || !$this->isEnabled()) {
            return;
        }

        $this->guard->assertSafe([
            'header' => $productFeed->getHeader(),
            'body' => $productFeed->getBody(),
            'footer' => $productFeed->getFooter(),
        ]);
    }

    /**
     * Returning true from an "Enlight_Controller_Action_<Module>_<Controller>_<action>"
     * listener makes Shopware skip the action (notifyUntil), so nothing is
     * saved or compiled; the backend receives success=false and the message.
     *
     * @param array<array-key, mixed> $fields
     */
    private function rejectRequest(Enlight_Controller_ActionEventArgs $args, array $fields): ?bool
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $violations = $this->guard->findViolations($fields);
        if ($violations === []) {
            return null;
        }

        $controller = $args->getSubject();
        if ($controller instanceof Enlight_Controller_Action) {
            $controller->View()->assign([
                'success' => false,
                'message' => $this->guard->buildMessage($violations),
            ]);
        }

        return true;
    }

    /**
     * @param array<array-key, mixed> $params
     * @param list<string>            $keys
     *
     * @return array<string, mixed>
     */
    private function pick(array $params, array $keys): array
    {
        return array_intersect_key($params, array_flip($keys));
    }

    private function isEnabled(): bool
    {
        return $this->configProvider->getForCurrentShop()->templateGuardEnabled;
    }
}
