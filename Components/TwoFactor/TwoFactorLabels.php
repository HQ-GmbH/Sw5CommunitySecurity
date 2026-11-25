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

namespace Sw5CommunitySecurity\Components\TwoFactor;

/**
 * Texts of the backend page "Two-factor login"; snippet key => English
 * default (namespace backend/sw5_community_security/messages).
 */
final class TwoFactorLabels
{
    public const LABELS = [
        'two_factor_title' => 'Two-factor login',
        'two_factor_intro' => 'Protects your backend account with a six-digit code from an authenticator app (TOTP) in addition to the password. The code is entered on the login page below the password.',
        'two_factor_module_off' => 'The module is switched off in the plugin settings; the login does not ask for codes.',
        'two_factor_status_on' => 'Two-factor login is set up for your account.',
        'two_factor_status_off' => 'Two-factor login is not set up for your account.',
        'two_factor_start' => 'Set up two-factor login',
        'two_factor_setup_step1' => 'Add an account in your authenticator app and enter this key manually (type: time-based):',
        'two_factor_setup_uri' => 'Alternatively, the complete set-up address:',
        'two_factor_setup_step2' => 'Enter the code shown in the app to confirm:',
        'two_factor_code' => 'Code',
        'two_factor_confirm' => 'Confirm and switch on',
        'two_factor_cancel' => 'Cancel',
        'two_factor_disable' => 'Switch off with code',
        'two_factor_users' => 'Backend users with two-factor login',
        'two_factor_reset' => 'Remove',
        'two_factor_result_enabled' => 'Two-factor login is now switched on. Keep the key in a safe place.',
        'two_factor_result_disabled' => 'Two-factor login is switched off.',
        'two_factor_result_invalid_code' => 'The code is not valid. Check the time on the device and try again.',
        'two_factor_result_reset' => 'The two-factor login of the user was removed.',
        'two_factor_result_denied' => 'Your backend role is not allowed to do this.',
        'two_factor_result_cancelled' => 'The set-up was cancelled.',
    ];
}
