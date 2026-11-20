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

/**
 * Stored content that Shopware 5.7 renders with Smarty, shared by the
 * template guard subscriber and the scan command. Sources are documented in
 * ROADMAP.md.
 */
final class GuardedSources
{
    /**
     * Basic settings (s_core_config_elements.name, compared case-insensitively)
     * whose values are rendered as Smarty templates: SEO URL templates
     * (Core/sRewriteTable.php), mail header/footer (included by every mail
     * template) and the "main features" template (checkout).
     */
    public const CONFIG_ELEMENTS = [
        'routerarticletemplate',
        'routercategorytemplate',
        'routerblogtemplate',
        'routercampaigntemplate',
        'seosupplierroutetemplate',
        'seoformroutetemplate',
        'seocustomsiteroutetemplate',
        'seostaticurls',
        'emailheaderhtml',
        'emailfooterhtml',
        'emailheaderplain',
        'emailfooterplain',
        'mainfeatures',
    ];

    /**
     * Translation types (s_core_translations.objecttype) containing Smarty
     * templates: mail templates, document boxes, payment means
     * (additional description) and countries (notice).
     */
    public const TRANSLATION_TYPES = ['config_mails', 'documents', 'config_payment', 'config_countries'];

    /** Repositories of Backend_Config::saveValues whose data contains Smarty templates. */
    public const CONFIG_VALUE_REPOSITORIES = ['document', 'country'];

    public static function isGuardedConfigElement(string $name): bool
    {
        return \in_array(strtolower($name), self::CONFIG_ELEMENTS, true);
    }
}
