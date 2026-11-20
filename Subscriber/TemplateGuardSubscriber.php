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
use Shopware\Models\Config\Value as ConfigValue;
use Shopware\Models\Country\Country;
use Shopware\Models\Document\Element as DocumentElement;
use Shopware\Models\Mail\Mail;
use Shopware\Models\Newsletter\Container as NewsletterContainer;
use Shopware\Models\Newsletter\ContainerType\Link as NewsletterLink;
use Shopware\Models\Newsletter\ContainerType\Text as NewsletterText;
use Shopware\Models\Newsletter\Newsletter;
use Shopware\Models\Payment\Payment;
use Shopware\Models\ProductFeed\ProductFeed;
use Sw5CommunitySecurity\Components\Config\PluginConfig;
use Sw5CommunitySecurity\Components\Config\PluginConfigProvider;
use Sw5CommunitySecurity\Components\TemplateGuard\GuardedSources;
use Sw5CommunitySecurity\Components\TemplateGuard\TemplateGuard;

/**
 * Module "Template guard".
 *
 * Rejects Smarty templates with disallowed constructs before Shopware stores
 * or compiles them:
 *
 * - Backend actions (clean error message in the backend, nothing is saved):
 *   mail templates and the "check Smarty" preview, product exports,
 *   newsletters, document boxes, countries, payment means, the basic
 *   settings listed in GuardedSources and translations of these.
 * - Doctrine lifecycle events of the affected models as a second line of
 *   defence for every other write path (REST API, other plugins).
 */
final class TemplateGuardSubscriber implements SubscriberInterface
{
    private const MAIL_FIELDS = ['subject', 'content', 'contentHtml', 'fromMail', 'fromName'];
    private const PRODUCT_FEED_FIELDS = ['header', 'body', 'footer'];

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
        $events = [
            'Enlight_Controller_Action_Backend_Mail_createMail' => 'onSaveMail',
            'Enlight_Controller_Action_Backend_Mail_updateMail' => 'onSaveMail',
            'Enlight_Controller_Action_Backend_Mail_verifySmarty' => 'onVerifySmarty',
            'Enlight_Controller_Action_Backend_ProductFeed_saveFeed' => 'onSaveProductFeed',
            'Enlight_Controller_Action_Backend_Translation_saveTranslation' => 'onSaveTranslation',
            'Enlight_Controller_Action_Backend_NewsletterManager_createNewsletter' => 'onSaveAllParams',
            'Enlight_Controller_Action_Backend_NewsletterManager_updateNewsletter' => 'onSaveAllParams',
            'Enlight_Controller_Action_Backend_Payment_createPayments' => 'onSaveAllParams',
            'Enlight_Controller_Action_Backend_Payment_updatePayments' => 'onSaveAllParams',
            'Enlight_Controller_Action_Backend_Config_saveValues' => 'onSaveConfigValues',
            'Enlight_Controller_Action_Backend_Config_saveForm' => 'onSaveConfigForm',
        ];

        foreach ([
            Mail::class, ProductFeed::class, Newsletter::class, NewsletterContainer::class, NewsletterText::class,
            NewsletterLink::class, DocumentElement::class, Payment::class, Country::class, ConfigValue::class,
        ] as $entityClass) {
            $events[$entityClass . '::prePersist'] = 'onEntity';
            $events[$entityClass . '::preUpdate'] = 'onEntity';
        }

        return $events;
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
        if (!\in_array((string) $request->getParam('type'), GuardedSources::TRANSLATION_TYPES, true)) {
            return null;
        }

        $data = $request->getParam('data', []);

        return $this->rejectRequest($args, \is_array($data) ? $data : []);
    }

    /**
     * Newsletters and payment means: the template fields sit at different
     * places of the request (e.g. containers[].text.content), so every value
     * is inspected. Other fields of these forms never contain Smarty tags.
     */
    public function onSaveAllParams(Enlight_Controller_ActionEventArgs $args): ?bool
    {
        return $this->rejectRequest($args, $args->getRequest()->getParams());
    }

    public function onSaveConfigValues(Enlight_Controller_ActionEventArgs $args): ?bool
    {
        $request = $args->getRequest();
        if (!\in_array((string) $request->getParam('_repositoryClass'), GuardedSources::CONFIG_VALUE_REPOSITORIES, true)) {
            return null;
        }

        return $this->rejectRequest($args, $request->getPost());
    }

    /**
     * Must run before Backend_Config::saveElement, which deletes the stored
     * values before writing the new ones.
     */
    public function onSaveConfigForm(Enlight_Controller_ActionEventArgs $args): ?bool
    {
        $elements = $args->getRequest()->getParam('elements');
        if (!\is_array($elements)) {
            return null;
        }

        $fields = [];
        foreach ($elements as $element) {
            if (!\is_array($element) || !\is_string($element['name'] ?? null) || !GuardedSources::isGuardedConfigElement($element['name'])) {
                continue;
            }

            foreach (\is_array($element['values'] ?? null) ? $element['values'] : [] as $value) {
                if (\is_array($value)) {
                    $fields[$element['name'] . '.' . ($value['shopId'] ?? '?')] = $value['value'] ?? null;
                }
            }
        }

        return $this->rejectRequest($args, $fields);
    }

    public function onEntity(Enlight_Event_EventArgs $args): void
    {
        $entity = $args->get('entity');
        if (!\is_object($entity)) {
            return;
        }

        $fields = $this->entityFields($entity);
        if ($fields === [] || !($config = $this->config())->templateGuardEnabled) {
            return;
        }

        $this->guard->assertSafe($fields, $config->templateGuardStrictIncludeMath);
    }

    /**
     * @return array<string, mixed>
     */
    private function entityFields(object $entity): array
    {
        return match (true) {
            $entity instanceof Mail => [
                'subject' => $entity->getSubject(),
                'content' => $entity->getContent(),
                'contentHtml' => $entity->getContentHtml(),
                'fromMail' => $entity->getFromMail(),
                'fromName' => $entity->getFromName(),
            ],
            $entity instanceof ProductFeed => [
                'header' => $entity->getHeader(),
                'body' => $entity->getBody(),
                'footer' => $entity->getFooter(),
            ],
            $entity instanceof Newsletter => [
                'subject' => $entity->getSubject(),
                'senderMail' => $entity->getSenderMail(),
                'senderName' => $entity->getSenderName(),
            ],
            $entity instanceof NewsletterContainer => ['description' => $entity->getDescription()],
            $entity instanceof NewsletterText => ['content' => $entity->getContent()],
            $entity instanceof NewsletterLink => ['description' => $entity->getDescription()],
            $entity instanceof DocumentElement => [(string) $entity->getName() => $entity->getValue()],
            $entity instanceof Payment => ['additionalDescription' => $entity->getAdditionalDescription()],
            $entity instanceof Country => ['notice' => $entity->getDescription()],
            $entity instanceof ConfigValue => $this->configValueFields($entity),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function configValueFields(ConfigValue $value): array
    {
        $element = $value->getElement();
        $name = $element !== null ? (string) $element->getName() : '';

        return GuardedSources::isGuardedConfigElement($name) ? [$name => $value->getValue()] : [];
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
        $config = $this->config();
        if (!$config->templateGuardEnabled) {
            return null;
        }

        $violations = $this->guard->findViolations($fields, $config->templateGuardStrictIncludeMath);
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

    private function config(): PluginConfig
    {
        return $this->configProvider->getForCurrentShop();
    }
}
