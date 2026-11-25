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

/*
 * Prints the TOTP code of a Base32 secret for the current time step plus an
 * offset, like an authenticator app; uses the plugin's own implementation,
 * which is unit-tested against the RFC 6238 test vectors.
 *
 * Usage: php Tests/Integration/totp.php <base32-secret> [step-offset]
 */

require __DIR__ . '/../../Components/TwoFactor/Base32.php';
require __DIR__ . '/../../Components/TwoFactor/Totp.php';

use Sw5CommunitySecurity\Components\TwoFactor\Base32;
use Sw5CommunitySecurity\Components\TwoFactor\Totp;

$secret = Base32::decode($argv[1] ?? '');
if ($secret === null || $secret === '') {
    fwrite(STDERR, "Usage: php totp.php <base32-secret> [step-offset]\n");
    exit(2);
}

echo Totp::code($secret, Totp::step(time()) + (int) ($argv[2] ?? 0));
