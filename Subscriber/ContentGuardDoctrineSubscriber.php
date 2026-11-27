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

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use LogicException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Supplier;
use Shopware\Models\Article\Vote;
use Shopware\Models\Blog\Blog;
use Shopware\Models\Category\Category;
use Shopware\Models\Emotion\Data as EmotionData;
use Shopware\Models\Form\Form;
use Shopware\Models\Newsletter\ContainerType\Text as NewsletterText;
use Shopware\Models\Site\Site;
use Sw5CommunitySecurity\Components\Config\PluginConfigProvider;
use Sw5CommunitySecurity\Components\ContentGuard\ContentGuard;
use Sw5CommunitySecurity\Components\ContentGuard\HtmlInspector;
use Sw5CommunitySecurity\Components\ContentGuard\UnsafeContentException;
use Sw5CommunitySecurity\Components\Database\RowReader;
use Sw5CommunitySecurity\Components\Messages\MessageFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Throwable;

/**
 * Module "Content check", Doctrine layer: rejects editor content (HTML) with
 * script when an entity is persisted or updated. Covers the backend, the REST
 * API and other plugins, because they all save these models via Doctrine.
 *
 * Registered as Doctrine event subscriber (DI tag "doctrine.event_subscriber",
 * engine/Shopware/Components/DependencyInjection/Compiler/DoctrineEventSubscriberCompilerPass.php
 * in Shopware 5.6.10 and 5.7.20), so it also sees the attribute entities,
 * whose classes are generated at runtime. Its services are fetched on first
 * use: Doctrine creates the subscriber together with the entity manager.
 */
final class ContentGuardDoctrineSubscriber implements EventSubscriber
{
    private const ATTRIBUTE_NAMESPACE = 'Shopware\\Models\\Attribute\\';

    /**
     * Marker in the class names of Doctrine proxies (Doctrine\Persistence\Proxy::MARKER):
     * "<proxy namespace>\__CG__\<entity class>".
     */
    private const PROXY_MARKER = '\\__CG__\\';

    /**
     * Shopping world element fields edited with TinyMCE (s_library_component_field.x_type).
     */
    private const EMOTION_EDITOR_XTYPE = 'tinymce';

    /**
     * @readonly
     */
    private ContainerInterface $container;

    private ?ContentGuard $guard = null;

    /**
     * Attribute table => columns of type "html" (s_attribute_configuration).
     *
     * @var array<string, list<string>>
     */
    private array $htmlAttributeColumns = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return list<string>
     */
    public function getSubscribedEvents(): array
    {
        return [Events::prePersist, Events::preUpdate];
    }

    /**
     * The event argument classes differ between the Doctrine versions of
     * Shopware 5.6 (ORM 2.6) and 5.7 (ORM 2.15); both provide getObject().
     */
    public function prePersist(object $args): void
    {
        $this->inspect($args);
    }

    public function preUpdate(object $args): void
    {
        $this->inspect($args);
    }

    private function inspect(object $args): void
    {
        $entity = method_exists($args, 'getObject') ? $args->getObject() : null;
        if (!\is_object($entity)) {
            return;
        }

        $fields = $this->entityFields($entity);
        // Updates: only fields that changed, so existing content does not
        // block unrelated changes of the same entity.
        $changeSet = method_exists($args, 'getEntityChangeSet') ? $args->getEntityChangeSet() : null;
        if (\is_array($changeSet)) {
            $fields = array_intersect_key($fields, $this->changedFields($entity, $changeSet));
        }
        if ($fields === [] || !$this->configProvider()->getForCurrentShop()->contentGuardEnabled) {
            return;
        }

        $guard = $this->guard();
        $violations = $guard->findViolations($fields);
        if ($violations === []) {
            return;
        }

        $rules = [];
        foreach ($violations as $field => $found) {
            $rules[$field] = $found;
        }
        // Only class, field names and rules: never content, user names or addresses.
        $this->logger()->warning('Content check rejected editor content.', ['source' => 'entity:' . \get_class($entity), 'rules' => $rules]);

        throw new UnsafeContentException($guard->buildMessage($violations));
    }

    /**
     * @return array<string, mixed>
     */
    private function entityFields(object $entity): array
    {
        switch (true) {
            case $entity instanceof Site:
                return ['html' => $entity->getHtml()];
            case $entity instanceof Article:
                return ['descriptionLong' => $entity->getDescriptionLong()];
            case $entity instanceof Category:
                return ['cmsText' => $entity->getCmsText()];
            case $entity instanceof Supplier:
                return ['description' => $entity->getDescription()];
            case $entity instanceof Blog:
                return ['description' => $entity->getDescription()];
            case $entity instanceof Form:
                return ['text' => $entity->getText(), 'text2' => $entity->getText2()];
            case $entity instanceof Vote:
                return ['answer' => $entity->getAnswer()];
            case $entity instanceof NewsletterText:
                return ['content' => $entity->getContent()];
            case $entity instanceof EmotionData:
                $field = $entity->getField();

                return $field !== null && $field->getXType() === self::EMOTION_EDITOR_XTYPE
                    ? [(string) $field->getName() => $entity->getValue()]
                    : [];
            case strncmp(self::realClass($entity), self::ATTRIBUTE_NAMESPACE, \strlen(self::ATTRIBUTE_NAMESPACE)) === 0:
                return $this->attributeFields($entity);
            default:
                return [];
        }
    }

    /**
     * Class of the entity, also for Doctrine proxies (lazy references).
     */
    private static function realClass(object $entity): string
    {
        $class = \get_class($entity);
        $marker = strpos($class, self::PROXY_MARKER);

        $real = $marker === false ? $class : substr($class, $marker + \strlen(self::PROXY_MARKER));

        return \is_string($real) && $real !== '' ? $real : $class;
    }

    /**
     * Field labels of entityFields() whose property is in the change set.
     *
     * @param array<array-key, mixed> $changeSet property => [old, new]
     *
     * @return array<string, true>
     */
    private function changedFields(object $entity, array $changeSet): array
    {
        $changed = [];
        foreach (array_keys($changeSet) as $property) {
            $changed[(string) $property] = true;
        }

        if ($entity instanceof EmotionData) {
            // The label is the field name; the stored property is "value".
            return isset($changed['value']) || isset($changed['field']) ? array_fill_keys(array_keys($this->entityFields($entity)), true) : [];
        }

        if (strncmp(self::realClass($entity), self::ATTRIBUTE_NAMESPACE, \strlen(self::ATTRIBUTE_NAMESPACE)) === 0) {
            // Labels are column names; map the changed properties to columns.
            $models = $this->container->get('models');
            $metadata = $models instanceof ModelManager ? $models->getClassMetadata(self::realClass($entity)) : null;
            if (!$metadata instanceof ClassMetadata) {
                return $changed;
            }

            $columns = [];
            foreach (array_keys($changed) as $property) {
                if ($metadata->hasField($property)) {
                    $columns[$metadata->getColumnName($property)] = true;
                }
            }

            return $columns;
        }

        return $changed;
    }

    /**
     * Values of the attribute columns configured as type "html" (edited with TinyMCE).
     *
     * @return array<string, mixed>
     */
    private function attributeFields(object $entity): array
    {
        $models = $this->container->get('models');
        $metadata = $models instanceof ModelManager ? $models->getClassMetadata(self::realClass($entity)) : null;
        if (!$metadata instanceof ClassMetadata) {
            return [];
        }

        $fields = [];
        foreach ($this->htmlColumns($metadata->getTableName()) as $column) {
            try {
                $fields[$column] = $metadata->getFieldValue($entity, $metadata->getFieldForColumn($column));
            } catch (Throwable $exception) {
                // Column not mapped (configuration without generated model): nothing to inspect.
            }
        }

        return $fields;
    }

    private function guard(): ContentGuard
    {
        if ($this->guard === null) {
            $formatter = $this->container->get(MessageFormatter::class);
            $this->guard = new ContentGuard(new HtmlInspector(), $formatter instanceof MessageFormatter ? $formatter : null);
        }

        return $this->guard;
    }

    private function configProvider(): PluginConfigProvider
    {
        $provider = $this->container->get(PluginConfigProvider::class);
        if (!$provider instanceof PluginConfigProvider) {
            throw new LogicException('Plugin configuration provider not available.');
        }

        return $provider;
    }

    private function logger(): LoggerInterface
    {
        $logger = $this->container->get('sw5_community_security.logger');

        return $logger instanceof LoggerInterface ? $logger : new NullLogger();
    }

    private function rows(): RowReader
    {
        $rows = $this->container->get(RowReader::class);
        if (!$rows instanceof RowReader) {
            throw new LogicException('Row reader not available.');
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function htmlColumns(string $table): array
    {
        if (!isset($this->htmlAttributeColumns[$table])) {
            $columns = [];
            foreach ($this->rows()->fetchAll("SELECT column_name FROM s_attribute_configuration WHERE table_name = ? AND column_type = 'html'", [$table]) as $row) {
                if (\is_string($row['column_name'] ?? null)) {
                    $columns[] = $row['column_name'];
                }
            }
            $this->htmlAttributeColumns[$table] = $columns;
        }

        return $this->htmlAttributeColumns[$table];
    }
}
