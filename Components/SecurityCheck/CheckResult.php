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

namespace Sw5CommunitySecurity\Components\SecurityCheck;

/**
 * Result of a single check. Texts are resolved through the snippet keys
 * "check_<id>" (title) and $messageKey; the English defaults are used if no
 * snippet is available.
 */
final class CheckResult
{
    public const STATUS_OK = 'ok';
    public const STATUS_INFO = 'info';
    public const STATUS_WARNING = 'warning';
    public const STATUS_ERROR = 'error';

    /**
     * Most severe first; used for sorting and the overall status.
     */
    public const SEVERITY_ORDER = [self::STATUS_ERROR, self::STATUS_WARNING, self::STATUS_INFO, self::STATUS_OK];

    /**
     * @readonly
     */
    public string $id;

    /**
     * @readonly
     *
     * @var self::STATUS_*
     */
    public string $status;

    /**
     * @readonly
     */
    public string $messageKey;

    /**
     * @readonly
     */
    public string $defaultMessage;

    /**
     * Raw values; escaping is up to the output.
     *
     * @readonly
     *
     * @var array<string, string>
     */
    public array $placeholders;

    /**
     * @param self::STATUS_*        $status
     * @param array<string, string> $placeholders
     */
    public function __construct(string $id, string $status, string $messageKey, string $defaultMessage, array $placeholders = [])
    {
        $this->id = $id;
        $this->status = $status;
        $this->messageKey = $messageKey;
        $this->defaultMessage = $defaultMessage;
        $this->placeholders = $placeholders;
    }
}
