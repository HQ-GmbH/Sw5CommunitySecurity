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

namespace Sw5CommunitySecurity\Tests\Unit\Support;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Keeps log records in memory for assertions.
 */
final class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{string, string, array<mixed>}>
     */
    public array $records = [];

    /**
     * @param mixed             $level
     * @param string|Stringable $message
     * @param array<mixed>      $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [\is_string($level) ? $level : '', (string) $message, $context];
    }
}
