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

use Shopware\Components\CSRFWhitelistAware;
use Sw5CommunitySecurity\Components\SecurityCheck\SecurityCheckRunner;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lightweight backend module (plain HTML in an iframe, see the Shopware 5
 * developer guide "Lightweight backend modules"). Login is enforced by the
 * core Auth plugin for every backend controller; access additionally
 * requires the ACL resource "sw5cssecuritycheck" (privilege "read"), which
 * only administrators have unless granted to other roles.
 */
class Shopware_Controllers_Backend_Sw5csSecurityCheck extends Enlight_Controller_Action implements CSRFWhitelistAware
{
    /**
     * The iframe is loaded with a plain GET request without CSRF token. The
     * action only reads and changes nothing.
     *
     * @return list<string>
     */
    public function getWhitelistedCSRFActions()
    {
        return ['index'];
    }

    public function indexAction(): void
    {
        $view = $this->View();
        if ($view === null) {
            return;
        }

        $runner = $this->get(SecurityCheckRunner::class);
        if (!$runner instanceof SecurityCheckRunner) {
            return;
        }

        $request = $this->Request();
        $rows = $runner->run($request instanceof Request ? $request : null);

        $view->loadTemplate('backend/sw5cs_security_check/index.tpl');
        $view->assign('sw5csRows', $rows);
        $view->assign('sw5csLabels', $runner->labels($rows));
    }
}
