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
use Shopware\Bundle\MediaBundle\MediaServiceInterface;
use Shopware\Models\Media\Media;
use Sw5CommunitySecurity\Components\Config\PluginConfigProvider;
use Sw5CommunitySecurity\Components\UploadGuard\UnsafeUploadException;
use Sw5CommunitySecurity\Components\UploadGuard\UploadInspector;
use Symfony\Component\HttpFoundation\Request;

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
    /** Backend controller/action pairs handled with a message (lower case). */
    private const KNOWN_UPLOADS = [
        'mediamanager/upload',
        'article/uploadesdfile',
        'snippet/importsnippet',
        'emotion/upload',
    ];

    private const EXCLUDED_CONTROLLERS = ['plugininstaller'];

    public function __construct(
        private readonly PluginConfigProvider $configProvider,
        private readonly UploadInspector $inspector,
        private readonly MediaServiceInterface $mediaService,
    ) {
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
        ];
    }

    public function onKnownUpload(Enlight_Controller_ActionEventArgs $args): ?bool
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $rejected = $this->findRejectedFiles();
        if ($rejected === []) {
            return null;
        }

        $controller = $args->getSubject();
        if ($controller instanceof Enlight_Controller_Action) {
            $controller->View()->assign(['success' => false, 'message' => $this->buildMessage($rejected)]);
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
        $blacklist = \is_array($blacklist) ? array_values($blacklist) : [];

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
        if ($file !== null && method_exists($file, 'getClientOriginalName')) {
            $name = (string) $file->getClientOriginalName();
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

        throw new UnsafeUploadException($this->buildMessage([['field' => 'media', 'name' => $name]]));
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
     * @return list<mixed>
     */
    private function flatten(mixed $value): array
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
    private function removeField(Request $request, string $field): void
    {
        $entry = $_FILES[$field] ?? null;
        if (\is_array($entry) && isset($entry['tmp_name'])) {
            foreach ($this->flatten($entry['tmp_name']) as $path) {
                if (\is_string($path) && $path !== '' && is_uploaded_file($path)) {
                    @unlink($path);
                }
            }
        }

        unset($_FILES[$field]);
        $request->files->remove($field);
    }

    /**
     * @param list<array{field: string, name: string}> $rejected
     */
    private function buildMessage(array $rejected): string
    {
        $names = array_unique(array_map(
            static fn (array $file): string => htmlspecialchars(basename($file['name']), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
            $rejected
        ));

        return 'Community Security Guard: Die Datei wurde abgelehnt, weil sie ausführbar sein oder ein PHAR-Archiv enthalten kann'
            . ' / The file was rejected because it may be executable or contain a PHAR archive ('
            . implode(', ', $names) . ').';
    }

    private function isEnabled(): bool
    {
        return $this->configProvider->getForCurrentShop()->uploadGuardEnabled;
    }
}
