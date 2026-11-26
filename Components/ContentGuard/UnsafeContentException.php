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

namespace Sw5CommunitySecurity\Components\ContentGuard;

use RuntimeException;

/**
 * Thrown when editor content (HTML) with script is about to be saved. The
 * message is shown in the Shopware backend or returned by the REST API; it
 * names fields and rules only and never echoes the content.
 */
final class UnsafeContentException extends RuntimeException
{
}
