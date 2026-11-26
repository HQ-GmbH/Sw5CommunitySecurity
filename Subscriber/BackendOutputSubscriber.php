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
use Enlight_Controller_ActionEventArgs;

/**
 * Backend views that show stored mail data (values of the last mail sent
 * from a template, subject, sender and recipients in the mail log) output it
 * as text instead of HTML.
 *
 * The Ext JS views are extended the documented way (template block "append"
 * plus Ext.define override), as in
 * themes/Backend/ExtJs/backend/mail/view/main/info.js (block
 * "backend/mail/view/main/info") and backend/mail_log/view/list/mail_log.js,
 * backend/mail_log/view/detail/form_mixin.js of Shopware 5.6.10 and 5.7.20.
 * Stored data and sent mails are not changed. Always active.
 */
final class BackendOutputSubscriber implements SubscriberInterface
{
    /**
     * Backend controller => templates extending its Ext JS application.
     */
    public const EXTENSIONS = [
        'mail' => [
            'backend/sw5cs_backend_output/mail/view/main/info.js',
        ],
        'maillog' => [
            'backend/sw5cs_backend_output/mail_log/view/list/mail_log.js',
            'backend/sw5cs_backend_output/mail_log/view/detail/form_mixin.js',
        ],
    ];

    /**
     * @readonly
     */
    private string $viewsDirectory;

    public function __construct(string $pluginDirectory)
    {
        $this->viewsDirectory = rtrim($pluginDirectory, '/') . '/Resources/views/';
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Mail' => 'onPostDispatch',
            'Enlight_Controller_Action_PostDispatchSecure_Backend_MailLog' => 'onPostDispatch',
        ];
    }

    public function onPostDispatch(Enlight_Controller_ActionEventArgs $args): void
    {
        $request = $args->getRequest();
        $action = strtolower((string) $request->getActionName());
        if ($action !== 'index' && $action !== 'load') {
            return;
        }

        $templates = self::EXTENSIONS[strtolower((string) $request->getControllerName())] ?? [];
        $view = $args->getSubject()->View();
        if ($templates === [] || $view === null) {
            return;
        }

        $view->addTemplateDir($this->viewsDirectory);
        foreach ($templates as $template) {
            $view->extendsTemplate($template);
        }
    }
}
