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
use Enlight_Controller_ActionEventArgs;
use Enlight_Controller_Request_Request;
use Enlight_Event_EventArgs;
use Psr\Log\LoggerInterface;
use Shopware\Bundle\MediaBundle\MediaServiceInterface;
use Shopware\Models\Media\Media;
use Sw5CommunitySecurity\Components\Compat\ActionEvent;
use Sw5CommunitySecurity\Components\Config\PluginConfigProvider;
use Sw5CommunitySecurity\Components\Messages\MessageFormatter;
use Sw5CommunitySecurity\Components\UploadGuard\UnsafeUploadException;
use Sw5CommunitySecurity\Components\UploadGuard\UploadInspector;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Module "Upload hardening".
 *
 * - Known backend uploads (media manager, ESD files, snippet import,
 *   shopping world import) are rejected with a message before Shopware
 *   stores the file.
 * - Any other request (frontend, widgets, backend, API) has offending files
 *   removed before the action runs; the plugin installer is excluded, since
 *   installing a plugin executes its code by design.
 * - The ESD upload blacklist is extended via Shopware's filter event.
 * - Media entities persisted by any other write path are checked after
 *   Shopware has written the file; an offending file is deleted again.
 */
final class UploadGuardSubscriber implements SubscriberInterface
{
    /**
     * Backend controller/action pairs handled with a message (lower case).
     */
    private const KNOWN_UPLOADS = [
        'mediamanager/upload',
        'article/uploadesdfile',
        'snippet/importsnippet',
        'emotion/upload',
    ];

    private const EXCLUDED_CONTROLLERS = ['plugininstaller'];

    /**
     * Media properties that change when the stored file is replaced
     * (Bundle/MediaBundle/MediaReplaceService.php).
     */
    private const MEDIA_FILE_PROPERTIES = ['path', 'extension', 'fileSize', 'created'];

    /**
     * @readonly
     */
    private PluginConfigProvider $configProvider;

    /**
     * @readonly
     */
    private UploadInspector $inspector;

    /**
     * @readonly
     */
    private MediaServiceInterface $mediaService;

    /**
     * @readonly
     */
    private MessageFormatter $formatter;

    /**
     * @readonly
     */
    private LoggerInterface $logger;

    public function __construct(PluginConfigProvider $configProvider, UploadInspector $inspector, MediaServiceInterface $mediaService, MessageFormatter $formatter, LoggerInterface $logger)
    {
        $this->configProvider = $configProvider;
        $this->inspector = $inspector;
        $this->mediaService = $mediaService;
        $this->formatter = $formatter;
        $this->logger = $logger;
    }

    /**
     * @return array<string, string|array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Enlight_Controller_Action_Backend_MediaManager_upload' => 'onKnownUpload',
            'Enlight_Controller_Action_Backend_Article_uploadEsdFile' => 'onKnownUpload',
            'Enlight_Controller_Action_Backend_Snippet_importSnippet' => 'onKnownUpload',
            'Enlight_Controller_Action_Backend_Emotion_upload' => 'onKnownUpload',
            'Enlight_Controller_Action_PreDispatch' => 'onPreDispatch',
            'Shopware_Controllers_Backend_Article_UploadEsdFile_Filter_EsdFileUploadBlacklist' => 'onFilterEsdBlacklist',
            Media::class . '::prePersist' => 'onMediaPersist',
            Media::class . '::preUpdate' => 'onMediaUpdate',
        ];
    }

    public function onKnownUpload(Enlight_Event_EventArgs $args): ?bool
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $rejected = $this->findRejectedFiles();
        if ($rejected === []) {
            return null;
        }

        $request = ActionEvent::request($args);
        $this->logRejection('backend:' . ($request !== null ? $request->getControllerName() . '/' . $request->getActionName() : 'unknown'), $rejected);

        $controller = ActionEvent::controller($args);
        // Shopware 5.6 declares View() as nullable.
        $view = $controller !== null ? $controller->View() : null;
        if ($view !== null) {
            $view->assign(['success' => false, 'message' => $this->buildMessage($rejected)]);
        }

        return true;
    }

    /**
     * Removes offending files from every other request before its action
     * runs, so no controller (Shopware or third-party) can store them.
     */
    public function onPreDispatch(Enlight_Controller_ActionEventArgs $args): void
    {
        if ($_FILES === [] || !$this->isEnabled()) {
            return;
        }

        $request = $args->getRequest();
        $controller = strtolower((string) $request->getControllerName());
        $isBackend = strtolower((string) $request->getModuleName()) === 'backend';

        if ($isBackend && (\in_array($controller, self::EXCLUDED_CONTROLLERS, true)
            || \in_array($controller . '/' . strtolower((string) $request->getActionName()), self::KNOWN_UPLOADS, true))) {
            return;
        }

        $rejected = $this->findRejectedFiles();
        if ($rejected === []) {
            return;
        }

        $this->logRejection(
            strtolower((string) $request->getModuleName()) . ':' . $controller . '/' . strtolower((string) $request->getActionName()),
            $rejected,
        );
        foreach (array_unique(array_column($rejected, 'field')) as $field) {
            $this->removeField($request, $field);
        }
    }

    /**
     * @param Enlight_Event_EventArgs $args return value: list of blocked extensions
     *
     * @return list<string>
     */
    public function onFilterEsdBlacklist(Enlight_Event_EventArgs $args): array
    {
        $blacklist = $args->getReturn();
        $blacklist = \is_array($blacklist) ? array_values(array_filter($blacklist, 'is_string')) : [];

        if (!$this->isEnabled()) {
            return $blacklist;
        }

        return array_values(array_unique(array_merge($blacklist, UploadInspector::BLOCKED_EXTENSIONS, UploadInspector::BLOCKED_FINAL_EXTENSIONS)));
    }

    /**
     * Shopware writes the file in Media::onSave (a lifecycle callback that
     * runs before this listener), so an offending file is deleted again
     * before the save is aborted.
     */
    public function onMediaPersist(Enlight_Event_EventArgs $args): void
    {
        $media = $args->get('entity');
        if (!$media instanceof Media || !$this->isEnabled()) {
            return;
        }

        $path = (string) $media->getPath();
        $name = $media->getName() . '.' . $media->getExtension();
        $file = $media->getFile();
        if ($file instanceof UploadedFile) {
            $name = $file->getClientOriginalName();
        }

        $blocked = $this->inspector->hasBlockedName($name) || $this->inspector->hasBlockedName('file.' . $media->getExtension());
        if (!$blocked && $path !== '' && $this->mediaService->has($path)) {
            $content = $this->mediaService->read($path);
            $blocked = \is_string($content) && $this->inspector->containsPharMarkerInString($content);
        }

        if (!$blocked) {
            return;
        }

        if ($path !== '' && $this->mediaService->has($path)) {
            $this->mediaService->delete($path);
        }

        $this->logRejection('entity:' . Media::class, [['field' => 'media', 'name' => $name]]);

        throw new UnsafeUploadException($this->buildMessage([['field' => 'media', 'name' => $name]]));
    }

    /**
     * Replacing the file of an existing medium (MediaReplaceService, e.g.
     * REST API PUT /api/media/<id> with "file") writes the new content and
     * updates the entity without persisting it again. The stored file is
     * inspected when the file-related fields change.
     */
    public function onMediaUpdate(Enlight_Event_EventArgs $args): void
    {
        $media = $args->get('entity');
        $entityManager = $args->get('entityManager');
        if (!$media instanceof Media || !$entityManager instanceof EntityManagerInterface) {
            return;
        }

        $changed = array_keys($entityManager->getUnitOfWork()->getEntityChangeSet($media));
        if (array_intersect(self::MEDIA_FILE_PROPERTIES, $changed) === []) {
            return;
        }

        $this->onMediaPersist($args);
    }

    /**
     * @return list<array{field: string, name: string}>
     */
    private function findRejectedFiles(): array
    {
        $rejected = [];
        foreach ($_FILES as $field => $entry) {
            if (!\is_array($entry) || !isset($entry['name'], $entry['tmp_name'])) {
                continue;
            }

            // Multi-file fields share the same structure for "name" and
            // "tmp_name", so flattening both yields matching pairs.
            $names = $this->flatten($entry['name']);
            $paths = $this->flatten($entry['tmp_name']);

            foreach ($names as $index => $name) {
                if (!\is_string($name) || $name === '') {
                    continue;
                }

                $path = $paths[$index] ?? null;
                $path = \is_string($path) && $path !== '' && is_uploaded_file($path) ? $path : null;
                if ($this->inspector->inspect($name, $path) !== null) {
                    $rejected[] = ['field' => (string) $field, 'name' => $name];
                }
            }
        }

        return $rejected;
    }

    /**
     * @param mixed $value
     *
     * @return list<mixed>
     */
    private function flatten($value): array
    {
        if (!\is_array($value)) {
            return [$value];
        }

        $flat = [];
        array_walk_recursive($value, static function ($leaf) use (&$flat): void {
            $flat[] = $leaf;
        });

        return $flat;
    }

    private function removeField(Enlight_Controller_Request_Request $request, string $field): void
    {
        $entry = $_FILES[$field] ?? null;
        if (\is_array($entry) && isset($entry['tmp_name'])) {
            foreach ($this->flatten($entry['tmp_name']) as $path) {
                // Suppressed warning: the result is checked and logged instead.
                if (\is_string($path) && $path !== '' && is_uploaded_file($path) && !@unlink($path)) {
                    $this->logger->warning('Upload guard could not delete a rejected temporary upload; PHP removes it at the end of the request.', ['field' => $field]);
                }
            }
        }

        unset($_FILES[$field]);
        // Shopware's HTTP request extends Symfony's request and keeps its own copy of the uploaded files.
        if ($request instanceof SymfonyRequest) {
            $request->files->remove($field);
        }
    }

    /**
     * @param list<array{field: string, name: string}> $rejected
     */
    private function buildMessage(array $rejected): string
    {
        $names = array_unique(array_map(
            static fn (array $file): string => basename(str_replace('\\', '/', $file['name'])),
            $rejected,
        ));

        return $this->formatter->format(
            'upload_rejected',
            'Community Security Guard: Die Datei wurde abgelehnt, weil sie ausführbar sein oder ein PHAR-Archiv enthalten kann'
            . ' / The file was rejected because it may be executable or contain a PHAR archive ([files]).',
            ['files' => implode(', ', $names)],
        );
    }

    /**
     * Logs a rejection to the plugin log. Only the source, form field and
     * file extensions are logged: never file names, contents, user names or
     * IP addresses.
     *
     * @param list<array{field: string, name: string}> $rejected
     */
    private function logRejection(string $source, array $rejected): void
    {
        $inspector = $this->inspector;
        $files = array_map(static function (array $file) use ($inspector): array {
            // Only the name parts that are blocked extensions: other parts
            // of a file name can contain personal data.
            $parts = explode('.', strtolower(basename(str_replace('\\', '/', $file['name']))));
            array_shift($parts);
            $blocked = [];
            foreach ($parts as $part) {
                if (preg_match('/^[a-z0-9]{1,16}$/', $part) === 1 && $inspector->hasBlockedName('file.' . $part)) {
                    $blocked[] = $part;
                }
            }

            return ['field' => $file['field'], 'extensions' => array_values(array_unique($blocked))];
        }, $rejected);

        $this->logger->warning('Upload guard rejected a file.', ['source' => $source, 'files' => $files]);
    }

    private function isEnabled(): bool
    {
        return $this->configProvider->getForCurrentShop()->uploadGuardEnabled;
    }
}
