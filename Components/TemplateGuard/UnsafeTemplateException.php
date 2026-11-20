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

namespace Sw5CommunitySecurity\Components\TemplateGuard;

use RuntimeException;

/**
 * Thrown when a template with disallowed Smarty constructs is about to be
 * saved. The message is shown in the Shopware backend; it names fields and
 * rules only and never echoes template content.
 */
final class UnsafeTemplateException extends RuntimeException
{
}
