{* This file is part of Sw5CommunitySecurity. (c) HQ GmbH, licensed under the GNU General Public License v3.0. *}
{block name="backend/base/header/javascript" append}
{if $user}
{* Removes script from content before it is loaded into the backend editor
   (TinyMCE, onBeforeSetContent), whatever way it was stored. The stored
   content only changes when it is saved; the storefront output is not changed. *}
{capture name="sw5csEditorNotice"}{s namespace="backend/sw5_community_security/messages" name="content_editor_cleaned"}Community Security Guard: Script was removed from this content for editing (e.g. script elements, event attributes, javascript: addresses). The stored content only changes when you save.{/s}{/capture}
<script type="text/javascript">
    window.sw5csEditorNotice = '{$smarty.capture.sw5csEditorNotice|escape:'javascript'}';
</script>
{literal}
<script type="text/javascript">
(function () {
    'use strict';

    if (!window.Ext || !Ext.ClassManager || !Ext.ClassManager.get('Shopware.form.field.TinyMCE') || window.sw5csEditorGuard) {
        return;
    }
    window.sw5csEditorGuard = true;

    var URL_ATTRIBUTES = ['href', 'src', 'action', 'formaction', 'data', 'poster', 'background', 'codebase',
        'cite', 'longdesc', 'lowsrc', 'dynsrc', 'xlink:href', 'xml:base', 'manifest', 'value'];
    var SCRIPT_URL = /^(?:javascript|vbscript|livescript|data:text\/html|data:application\/xhtml|data:text\/xml)/i;

    // Same rules as the server-side content check (HtmlInspector). DOMParser
    // creates an inert document: nothing in it is loaded or executed.
    function clean(html) {
        if (typeof html !== 'string' || html.indexOf('<') === -1 || typeof window.DOMParser !== 'function') {
            return null;
        }

        var doc = new window.DOMParser().parseFromString('<!DOCTYPE html><html><head></head><body>' + html + '</body></html>', 'text/html');
        var removed = false;

        Array.prototype.slice.call(doc.body.querySelectorAll('script, noscript')).forEach(function (element) {
            element.parentNode.removeChild(element);
            removed = true;
        });
        Array.prototype.slice.call(doc.body.querySelectorAll('*')).forEach(function (element) {
            Array.prototype.slice.call(element.attributes).forEach(function (attribute) {
                var name = attribute.name.toLowerCase();
                if (name.indexOf('on') === 0 || name === 'srcdoc'
                    || (URL_ATTRIBUTES.indexOf(name) !== -1 && SCRIPT_URL.test(attribute.value.replace(/[\x00-\x20\x7f]+/g, '')))) {
                    element.removeAttribute(attribute.name);
                    removed = true;
                }
            });
        });

        // Unchanged content keeps its exact markup.
        return removed ? doc.body.innerHTML : null;
    }

    window.sw5csCleanEditorContent = clean;

    Ext.define('Shopware.form.field.Sw5csTinyMCE', {
        override: 'Shopware.form.field.TinyMCE',

        initComponent: function () {
            var me = this;

            me.callParent(arguments);
            me.on('rendereditor', function (field, editor) {
                if (!editor || !editor.onBeforeSetContent) {
                    return;
                }
                editor.onBeforeSetContent.add(function (ed, options) {
                    var cleaned = clean(options.content);
                    if (cleaned === null) {
                        return;
                    }
                    options.content = cleaned;
                    if (window.Shopware && Shopware.Notification && window.sw5csEditorNotice) {
                        Shopware.Notification.createGrowlMessage('', window.sw5csEditorNotice, '');
                    }
                });
            });
        }
    });
}());
</script>
{/literal}
{/if}
{/block}
