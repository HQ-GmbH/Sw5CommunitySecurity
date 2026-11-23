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

namespace Sw5CommunitySecurity\Components\Messages;

use Shopware_Components_Snippet_Manager;

/**
 * Reads texts from the Shopware snippet system (Resources/snippets), so shop
 * administrators can edit and translate them. The namespace is resolved on
 * every call, because the backend sets the user's locale only after the
 * service has been created.
 */
final class SnippetMessageCatalog implements MessageCatalog
{
    public const SNIPPET_NAMESPACE = 'backend/sw5_community_security/messages';

    public function __construct(
        private readonly Shopware_Components_Snippet_Manager $snippets,
    ) {
    }

    public function get(string $key, string $default): string
    {
        $value = $this->snippets->getNamespace(self::SNIPPET_NAMESPACE)->get($key, $default);

        return \is_string($value) && $value !== '' ? $value : $default;
    }
}
