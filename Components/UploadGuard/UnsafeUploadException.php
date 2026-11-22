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

namespace Sw5CommunitySecurity\Components\UploadGuard;

use RuntimeException;

/**
 * Thrown when an uploaded file is rejected by the upload hardening module.
 * The message names the file but never its content.
 */
final class UnsafeUploadException extends RuntimeException
{
}
