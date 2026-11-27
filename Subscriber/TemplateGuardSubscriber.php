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

use Doctrine\ORM\EntityManagerInterface;
use Enlight\Event\SubscriberInterface;
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
use Sw5CommunitySecurity\Components\Compat\ActionEvent;
use Sw5CommunitySecurity\Components\Config\PluginConfig;
use Sw5CommunitySecurity\Components\Config\PluginConfigProvider;
use Sw5CommunitySecurity\Components\TemplateGuard\Finding;
use Sw5CommunitySecurity\Components\TemplateGuard\GuardedSources;
use Sw5CommunitySecurity\Components\TemplateGuard\SnippetImportValues;
use Sw5CommunitySecurity\Components\TemplateGuard\TemplateGuard;
use Sw5CommunitySecurity\Components\TemplateGuard\UnsafeTemplateException;

/**
 * Module "Template guard".
 *
 * Rejects Smarty templates with disallowed constructs before Shopware stores
 * or compiles them:
 *
 * - Backend actions (clean error message in the backend, nothing is saved):
 *   mail templates and the "check Smarty" preview, the mail to a merchant
 *   from the dashboard, product exports, newsletters, document boxes,
 *   countries, payment means, the basic settings listed in GuardedSources,
 *   snippets (Shopware compiles every snippet value into the template
 *   source; also the import), shopping worlds, content types (SEO URL
 *   template) and translations of every type, including the translation
 *   import of shopping worlds.
 * - REST API translations (Shopware writes them with plain SQL): rejected
 *   with status 400.
 * - Doctrine lifecycle events of the affected models as a second line of
 *   defence for every other write path (REST API, other plugins). Updates
 *   are inspected only if a template field changed, so stored content is
 *   not re-checked on unrelated writes (e.g. the mail context when sending).
 */
final class TemplateGuardSubscriber implements SubscriberInterface
{
    private const MAIL_FIELDS = ['subject', 'content', 'contentHtml', 'fromMail', 'fromName'];
    private const PRODUCT_FEED_FIELDS = ['header', 'body', 'footer'];
    private const MERCHANT_MAIL_FIELDS = ['fromMail', 'fromName', 'subject', 'content'];

    private const HTTP_BAD_REQUEST = 400;

    private const API_WRITE_ACTIONS = ['post', 'put', 'batch'];

    /**
     * REST API resources whose "translations" part Shopware writes with
     * plain SQL (Components/Api/Resource/Article.php, Category.php).
     */
    private const API_TRANSLATED_ENTITIES = ['Articles', 'Variants', 'Categories'];

    /**
     * Mapped properties holding template text, per entity class; an update
     * is inspected only if one of them is in the change set.
     */
    private const TEMPLATE_PROPERTIES = [
        Mail::class => ['subject', 'content', 'contentHtml', 'fromMail', 'fromName'],
        ProductFeed::class => ['header', 'body', 'footer'],
        Newsletter::class => ['subject', 'senderMail', 'senderName'],
        NewsletterContainer::class => ['description'],
        NewsletterText::class => ['content'],
        NewsletterLink::class => ['description'],
        DocumentElement::class => ['name', 'value'],
        Payment::class => ['additionalDescription'],
        Country::class => ['description'],
        ConfigValue::class => ['value', 'element'],
        Snippet::class => ['value', 'namespace', 'name'],
        EmotionData::class => ['value', 'field'],
    ];

    /**
     * @readonly
     */
    private PluginConfigProvider $configProvider;

    /**
     * @readonly
     */
    private TemplateGuard $guard;

    /**
     * @readonly
     */
    private LoggerInterface $logger;

    public function __construct(PluginConfigProvider $configProvider, TemplateGuard $guard, LoggerInterface $logger)
    {
        $this->configProvider = $configProvider;
        $this->guard = $guard;
        $this->logger = $logger;
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
            'Enlight_Controller_Action_Backend_Emotion_importTranslations' => 'onImportEmotionTranslations',
            'Enlight_Controller_Action_Backend_Widgets_sendMailToMerchant' => 'onSendMailToMerchant',
            'Enlight_Controller_Action_Backend_ContentTypeManager_create' => 'onSaveAllParams',
            'Enlight_Controller_Action_Backend_ContentTypeManager_update' => 'onSaveAllParams',
        ];

        foreach (self::API_WRITE_ACTIONS as $action) {
            $events['Enlight_Controller_Action_Api_Translations_' . $action] = 'onApiTranslations';
            foreach (self::API_TRANSLATED_ENTITIES as $controller) {
                $events['Enlight_Controller_Action_Api_' . $controller . '_' . $action] = 'onApiEntityTranslations';
            }
        }

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

    public function onSaveMail(Enlight_Event_EventArgs $args): ?bool
    {
        $request = ActionEvent::request($args);

        return $request === null ? null : $this->rejectRequest($args, $this->pick($request->getParams(), self::MAIL_FIELDS));
    }

    public function onVerifySmarty(Enlight_Event_EventArgs $args): ?bool
    {
        $request = ActionEvent::request($args);

        return $request === null ? null : $this->rejectRequest($args, ['value' => $request->getParam('value')]);
    }

    public function onSaveProductFeed(Enlight_Event_EventArgs $args): ?bool
    {
        $request = ActionEvent::request($args);

        return $request === null ? null : $this->rejectRequest($args, $this->pick($request->getParams(), self::PRODUCT_FEED_FIELDS));
    }

    /**
     * Every translation type is inspected: the database compares the type
     * case- and accent-insensitively (s_core_translations.objecttype,
     * utf8_unicode_ci), so a list of guarded types could be bypassed by
     * spelling. The rules only match vulnerable constructs.
     */
    public function onSaveTranslation(Enlight_Event_EventArgs $args): ?bool
    {
        $request = ActionEvent::request($args);
        if ($request === null) {
            return null;
        }

        $data = $request->getParam('data', []);

        return $this->rejectRequest($args, \is_array($data) ? $data : ['data' => $data]);
    }

    /**
     * Translation import of shopping worlds: Shopware decodes the JSON and
     * writes every entry with plain SQL, whatever its type
     * (Components/Emotion/EmotionTranslationImporter.php).
     */
    public function onImportEmotionTranslations(Enlight_Event_EventArgs $args): ?bool
    {
        $request = ActionEvent::request($args);
        if ($request === null) {
            return null;
        }

        return $this->rejectRequest($args, self::emotionTranslationFields($request->getParam('emotionTranslations')));
    }

    /**
     * @param mixed $json request parameter "emotionTranslations"
     *
     * @return array<string, mixed>
     */
    public static function emotionTranslationFields($json): array
    {
        if (!\is_string($json)) {
            return ['emotionTranslations' => $json];
        }

        $entries = json_decode($json, true);
        if (!\is_array($entries)) {
            return ['emotionTranslations' => $json];
        }

        $fields = [];
        foreach ($entries as $index => $entry) {
            $data = \is_array($entry) ? ($entry['objectdata'] ?? null) : $entry;
            if (\is_string($data)) {
                $unserialized = @unserialize($data, ['allowed_classes' => false]);
                $data = $unserialized !== false || $data === serialize(false) ? $unserialized : $data;
            }
            $fields['translation ' . $index] = $data;
        }

        return $fields;
    }

    public function onSendMailToMerchant(Enlight_Event_EventArgs $args): ?bool
    {
        $request = ActionEvent::request($args);

        return $request === null ? null : $this->rejectRequest($args, $this->pick($request->getParams(), self::MERCHANT_MAIL_FIELDS));
    }

    /**
     * /api/translations: every value sent is inspected.
     */
    public function onApiTranslations(Enlight_Event_EventArgs $args): ?bool
    {
        $request = ActionEvent::request($args);
        $post = $request !== null ? $request->getPost() : null;

        return \is_array($post) ? $this->rejectApi($args, $post) : null;
    }

    /**
     * Products, variants, categories: the "translations" part, which bypasses Doctrine.
     */
    public function onApiEntityTranslations(Enlight_Event_EventArgs $args): ?bool
    {
        $request = ActionEvent::request($args);
        if ($request === null) {
            return null;
        }

        $translations = [];
        ContentGuardSubscriber::collectTranslations($request->getPost(), '', 0, $translations);

        return $translations === [] ? null : $this->rejectApi($args, $translations);
    }

    /**
     * Newsletters and payment means: the template fields sit at different
     * places of the request (e.g. containers[].text.content), so every value
     * is inspected. Other fields of these forms never contain Smarty tags.
     */
    public function onSaveAllParams(Enlight_Event_EventArgs $args): ?bool
    {
        $request = ActionEvent::request($args);

        return $request === null ? null : $this->rejectRequest($args, $request->getParams());
    }

    public function onSaveConfigValues(Enlight_Event_EventArgs $args): ?bool
    {
        $request = ActionEvent::request($args);
        if ($request === null || !\in_array($request->getParam('_repositoryClass'), GuardedSources::CONFIG_VALUE_REPOSITORIES, true)) {
            return null;
        }

        $post = $request->getPost();

        return $this->rejectRequest($args, \is_array($post) ? $post : []);
    }

    /**
     * Snippet import (CSV; XML in Shopware 5.6): every value is inspected in
     * the form Shopware stores it (entities decoded) before the plain SQL
     * insert, see SnippetImportValues.
     */
    public function onImportSnippets(Enlight_Event_EventArgs $args): ?bool
    {
        $upload = $_FILES['file'] ?? null;
        $file = \is_array($upload) ? ($upload['tmp_name'] ?? null) : null;
        $name = \is_array($upload) ? ($upload['name'] ?? null) : null;
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

        return $this->rejectRequest($args, ['raw file' => $content] + SnippetImportValues::fromFile($content, \is_string($name) ? $name : ''));
    }

    /**
     * Must run before Backend_Config::saveElement, which deletes the stored
     * values before writing the new ones.
     */
    public function onSaveConfigForm(Enlight_Event_EventArgs $args): ?bool
    {
        $request = ActionEvent::request($args);
        $elements = $request !== null ? $request->getParam('elements') : null;
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

        if (substr((string) $args->getName(), -\strlen('::preUpdate')) === '::preUpdate' && !$this->templateChanged($entity, $args->get('entityManager'))) {
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

        $this->logRejection('entity:' . \get_class($entity), $violations);

        throw new UnsafeTemplateException($this->guard->buildMessage($violations));
    }

    /**
     * @param mixed $entityManager
     */
    private function templateChanged(object $entity, $entityManager): bool
    {
        if (!$entityManager instanceof EntityManagerInterface) {
            // Without a change set, inspect as before (fail closed).
            return true;
        }

        $changeSet = $entityManager->getUnitOfWork()->getEntityChangeSet($entity);
        foreach (self::TEMPLATE_PROPERTIES as $class => $properties) {
            if ($entity instanceof $class) {
                return array_intersect($properties, array_keys($changeSet)) !== [];
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function entityFields(object $entity): array
    {
        switch (true) {
            case $entity instanceof Mail:
                return [
                    'subject' => $entity->getSubject(),
                    'content' => $entity->getContent(),
                    'contentHtml' => $entity->getContentHtml(),
                    'fromMail' => $entity->getFromMail(),
                    'fromName' => $entity->getFromName(),
                ];
            case $entity instanceof ProductFeed:
                return [
                    'header' => $entity->getHeader(),
                    'body' => $entity->getBody(),
                    'footer' => $entity->getFooter(),
                ];
            case $entity instanceof Newsletter:
                return [
                    'subject' => $entity->getSubject(),
                    'senderMail' => $entity->getSenderMail(),
                    'senderName' => $entity->getSenderName(),
                ];
            case $entity instanceof NewsletterContainer:
                return ['description' => $entity->getDescription()];
            case $entity instanceof NewsletterText:
                return ['content' => $entity->getContent()];
            case $entity instanceof NewsletterLink:
                return ['description' => $entity->getDescription()];
            case $entity instanceof DocumentElement:
                return [(string) $entity->getName() => $entity->getValue()];
            case $entity instanceof Payment:
                return ['additionalDescription' => $entity->getAdditionalDescription()];
            case $entity instanceof Country:
                return ['notice' => $entity->getDescription()];
            case $entity instanceof ConfigValue:
                return $this->configValueFields($entity);
            case $entity instanceof Snippet:
                return [(string) $entity->getNamespace() . '/' . $entity->getName() => $entity->getValue()];
            case $entity instanceof EmotionData:
                return $this->emotionDataFields($entity);
            default:
                return [];
        }
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
    private function rejectRequest(Enlight_Event_EventArgs $args, array $fields): ?bool
    {
        $config = $this->config();
        if (!$config->templateGuardEnabled) {
            return null;
        }

        $violations = $this->guard->findViolations($fields, $config->templateGuardStrictIncludeMath);

        return $violations === [] ? null : $this->reject($args, $violations);
    }

    /**
     * @param array<array-key, list<Finding>> $violations field path => findings (numeric paths are int keys)
     */
    private function reject(Enlight_Event_EventArgs $args, array $violations): bool
    {
        $request = ActionEvent::request($args);
        $this->logRejection(
            'backend:' . ($request !== null ? $request->getControllerName() . '/' . $request->getActionName() : 'unknown'),
            $violations,
        );

        $controller = ActionEvent::controller($args);
        // Shopware 5.6 declares View() as nullable.
        $view = $controller !== null ? $controller->View() : null;
        if ($view !== null) {
            $view->assign([
                'success' => false,
                'message' => $this->guard->buildMessage($violations),
            ]);
        }

        return true;
    }

    /**
     * REST API: status 400 and the message, nothing is written.
     *
     * @param array<array-key, mixed> $fields
     */
    private function rejectApi(Enlight_Event_EventArgs $args, array $fields): ?bool
    {
        $config = $this->config();
        if (!$config->templateGuardEnabled) {
            return null;
        }

        $violations = $this->guard->findViolations($fields, $config->templateGuardStrictIncludeMath);
        if ($violations === []) {
            return null;
        }

        $request = ActionEvent::request($args);
        $this->logRejection('api:' . ($request !== null ? strtolower((string) $request->getControllerName()) : 'unknown'), $violations);

        $controller = ActionEvent::controller($args);
        if ($controller !== null) {
            $controller->Response()->setStatusCode(self::HTTP_BAD_REQUEST);
            // Shopware 5.6 declares View() as nullable.
            $view = $controller->View();
            if ($view !== null) {
                $view->assign(['success' => false, 'message' => $this->guard->buildMessage($violations)]);
            }
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
     * @param array<array-key, list<Finding>> $violations field path => findings (numeric paths are int keys)
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
