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

namespace Sw5CommunitySecurity\Components\ProtectionFiles;

/**
 * Outcome of ProtectionFiles::restore(); paths are relative to the Shopware
 * root.
 */
final class RestoreResult
{
    /**
     * @readonly
     *
     * @var list<string>
     */
    public array $restored;

    /**
     * Files that could not be created (directory not writable, or something
     * other than a regular file is in the way).
     *
     * @readonly
     *
     * @var list<string>
     */
    public array $failed;

    /**
     * @param list<string> $restored
     * @param list<string> $failed
     */
    public function __construct(array $restored, array $failed)
    {
        $this->restored = $restored;
        $this->failed = $failed;
    }

    public function hasChanges(): bool
    {
        return $this->restored !== [] || $this->failed !== [];
    }
}
