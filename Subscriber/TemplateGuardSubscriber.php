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
use Psr\Log\LoggerInterface;
use Shopware\Models\Config\Value as ConfigValue;
use Shopware\Models\Country\Country;
use Shopware\Models\Document\Element as DocumentElement;
use Shopware\Models\Emotion\Data as EmotionData;
use Shopware\Models\Mail\Mail;
use Shopware\Models\Newsletter\Container as NewsletterContainer;
use Shopware\Models\Newsletter\ContainerType\Link as NewsletterLink;
use Shopware\Models\Newsletter\ContainerType\Text as NewsletterText;
use Shopware\Models\Newsletter\Newsletter;
use Shopware\Models\Payment\Payment;
use Shopware\Models\ProductFeed\ProductFeed;
use Shopware\Models\Snippet\Snippet;
use Sw5CommunitySecurity\Components\Config\PluginConfig;
use Sw5CommunitySecurity\Components\Config\PluginConfigProvider;
use Sw5CommunitySecurity\Components\TemplateGuard\Finding;
use Sw5CommunitySecurity\Components\TemplateGuard\GuardedSources;
use Sw5CommunitySecurity\Components\TemplateGuard\TemplateGuard;
use Sw5CommunitySecurity\Components\TemplateGuard\UnsafeTemplateException;

/**
 * Module "Template guard".
 *
 * Rejects Smarty templates with disallowed constructs before Shopware stores
 * or compiles them:
 *
 * - Backend actions (clean error message in the backend, nothing is saved):
 *   mail templates and the "check Smarty" preview, product exports,
 *   newsletters, document boxes, countries, payment means, the basic
 *   settings listed in GuardedSources, snippets (Shopware compiles every
 *   snippet value into the template source), shopping worlds and
 *   translations of these.
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
        private readonly LoggerInterface $logger,
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
            'Enlight_Controller_Action_Backend_Snippet_createSnippet' => 'onSaveAllParams',
            'Enlight_Controller_Action_Backend_Snippet_updateSnippet' => 'onSaveAllParams',
            'Enlight_Controller_Action_Backend_Snippet_importSnippet' => 'onImportSnippets',
            'Enlight_Controller_Action_Backend_Emotion_save' => 'onSaveAllParams',
            'Enlight_Controller_Action_Backend_Emotion_savePreview' => 'onSaveAllParams',
        ];

        foreach ([
            Mail::class, ProductFeed::class, Newsletter::class, NewsletterContainer::class, NewsletterText::class,
            NewsletterLink::class, DocumentElement::class, Payment::class, Country::class, ConfigValue::class,
            Snippet::class, EmotionData::class,
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
        if (!\in_array($request->getParam('type'), GuardedSources::TRANSLATION_TYPES, true)) {
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
        if (!\in_array($request->getParam('_repositoryClass'), GuardedSources::CONFIG_VALUE_REPOSITORIES, true)) {
            return null;
        }

        $post = $request->getPost();

        return $this->rejectRequest($args, \is_array($post) ? $post : []);
    }

    /**
     * Snippet import (CSV): the uploaded file is inspected as a whole before
     * Shopware writes it to the database with plain SQL.
     */
    public function onImportSnippets(Enlight_Controller_ActionEventArgs $args): ?bool
    {
        $upload = $_FILES['file'] ?? null;
        $file = \is_array($upload) ? ($upload['tmp_name'] ?? null) : null;
        if (!\is_string($file) || $file === '' || !is_file($file)) {
            return null;
        }

        $size = filesize($file);
        $content = $size !== false && $size <= GuardedSources::MAX_IMPORT_FILE_SIZE ? file_get_contents($file) : false;
        if ($content === false) {
            return $this->config()->templateGuardEnabled
                ? $this->reject($args, ['file' => [new Finding(Finding::RULE_TOO_LARGE, '')]])
                : null;
        }

        return $this->rejectRequest($args, ['file' => $content]);
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
                    $shopId = $value['shopId'] ?? null;
                    $fields[$element['name'] . '.' . (\is_scalar($shopId) ? (string) $shopId : '?')] = $value['value'] ?? null;
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

        $violations = $this->guard->findViolations($fields, $config->templateGuardStrictIncludeMath);
        if ($violations === []) {
            return;
        }

        $this->logRejection('entity:' . $entity::class, $violations);

        throw new UnsafeTemplateException($this->guard->buildMessage($violations));
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
            $entity instanceof Snippet => [(string) $entity->getNamespace() . '/' . $entity->getName() => $entity->getValue()],
            $entity instanceof EmotionData => $this->emotionDataFields($entity),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function emotionDataFields(EmotionData $data): array
    {
        $field = $data->getField();

        return $field !== null && $field->getName() === GuardedSources::EMOTION_SMARTY_FIELD
            ? [GuardedSources::EMOTION_SMARTY_FIELD => $data->getValue()]
            : [];
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

        return $violations === [] ? null : $this->reject($args, $violations);
    }

    /**
     * @param array<string, list<Finding>> $violations
     */
    private function reject(Enlight_Controller_ActionEventArgs $args, array $violations): bool
    {
        $request = $args->getRequest();
        $this->logRejection(
            'backend:' . $request->getControllerName() . '/' . $request->getActionName(),
            $violations
        );

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

    /**
     * Logs a rejection to the plugin log. Only the source, field names and
     * rule identifiers are logged: never template content, user names or IP
     * addresses.
     *
     * @param array<string, list<Finding>> $violations
     */
    private function logRejection(string $source, array $violations): void
    {
        $rules = [];
        foreach ($violations as $field => $findings) {
            $rules[$field] = array_values(array_unique(array_map(static fn (Finding $finding): string => $finding->rule, $findings)));
        }

        $this->logger->warning('Template guard rejected a template.', ['source' => $source, 'rules' => $rules]);
    }

    private function config(): PluginConfig
    {
        return $this->configProvider->getForCurrentShop();
    }
}
