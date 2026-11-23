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

namespace Sw5CommunitySecurity\Components\Compat;

use Enlight_Controller_Action;
use Enlight_Controller_Request_Request;
use Enlight_Event_EventArgs;

/**
 * Reads controller and request from "Enlight_Controller_Action_<Module>_<Controller>_<action>"
 * events in both Shopware versions: Shopware 5.7 passes
 * Enlight_Controller_ActionEventArgs, Shopware 5.6 plain Enlight_Event_EventArgs
 * with only the "subject" (engine/Library/Enlight/Controller/Action.php:187 in v5.6.10).
 */
final class ActionEvent
{
    public static function controller(Enlight_Event_EventArgs $args): ?Enlight_Controller_Action
    {
        $subject = $args->get('subject');

        return $subject instanceof Enlight_Controller_Action ? $subject : null;
    }

    public static function request(Enlight_Event_EventArgs $args): ?Enlight_Controller_Request_Request
    {
        $controller = self::controller($args);

        return $controller !== null ? $controller->Request() : null;
    }
}
