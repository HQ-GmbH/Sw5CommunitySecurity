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

namespace Sw5CommunitySecurity\Components\JavaScriptEscape;

use Sw5CommunitySecurity\Components\Config\DefaultShopConfigSource;
use Throwable;

/**
 * Smarty post filter for compiled template code.
 *
 * Registered on Shopware's "template" service (see
 * DependencyInjection\TemplateFilterCompilerPass); Smarty runs post filters on
 * the compiled code of every template before it is stored
 * (smarty_internal_templatecompilerbase.php, compileTemplate()). Clones of the
 * template engine (documents, newsletters, product exports) inherit it.
 *
 * Compiled templates are shared by all shops, so the module switch of the
 * default shop applies. If the configuration cannot be read, the hardening
 * stays active.
 */
final class CompiledTemplateFilter
{
    /**
     * @readonly
     */
    private DefaultShopConfigSource $configSource;

    /**
     * @readonly
     */
    private JavaScriptEscapeHardening $hardening;

    public function __construct(DefaultShopConfigSource $configSource, JavaScriptEscapeHardening $hardening)
    {
        $this->configSource = $configSource;
        $this->hardening = $hardening;
    }

    /**
     * Signature of a Smarty 3.1 filter callback: ($content, $template).
     *
     * @param mixed $template Smarty_Internal_Template, unused
     */
    public function filter(string $compiledCode, $template = null): string
    {
        $registered = self::registeredEscape($template);
        if ((!JavaScriptEscapeHardening::mayNeedHardening($compiledCode) && $registered === null) || !$this->isEnabled()) {
            return $compiledCode;
        }

        return $this->hardening->harden($compiledCode, $registered);
    }

    /**
     * Callable registered as modifier "escape" for the template (Smarty passes
     * the template object to post filters), or null.
     *
     * @param mixed $template
     *
     * @return mixed
     */
    private static function registeredEscape($template)
    {
        $smarty = \is_object($template) && isset($template->smarty) && \is_object($template->smarty) ? $template->smarty : null;
        $plugins = $smarty !== null && isset($smarty->registered_plugins) && \is_array($smarty->registered_plugins) ? $smarty->registered_plugins : [];
        $modifiers = $plugins['modifier'] ?? null;
        $escape = \is_array($modifiers) ? ($modifiers['escape'] ?? null) : null;

        return \is_array($escape) ? ($escape[0] ?? null) : null;
    }

    private function isEnabled(): bool
    {
        try {
            return $this->configSource->getForDefaultShop()->javaScriptEscapeEnabled;
        } catch (Throwable $exception) {
            return true;
        }
    }
}
