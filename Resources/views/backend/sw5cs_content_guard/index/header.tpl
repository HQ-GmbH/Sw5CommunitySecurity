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
    // "value" only counts on <param>. Matched after the parser decoded entities
    // and control characters and whitespace were removed.
    var VALUE_URL_ELEMENT = 'param';
    var SCRIPT_URL = /^(?:javascript:|vbscript:|livescript:|data:text\/html|data:application\/xhtml|data:text\/xml)/i;

    // Same rules as the server-side content check (HtmlInspector). DOMParser
    // creates an inert document: nothing in it is loaded or executed.
    function clean(html) {
        if (typeof html !== 'string' || html.indexOf('<') === -1 || typeof window.DOMParser !== 'function') {
            return null;
        }

        var doc = new window.DOMParser().parseFromString('<!DOCTYPE html><html><head></head><body>' + html + '</body></html>', 'text/html');

        // Unchanged content keeps its exact markup.
        return cleanTree(doc.body) ? doc.body.innerHTML : null;
    }

    // Cleans an element or document fragment. querySelectorAll() does not
    // look into <template> elements, so their content is cleaned separately.
    function cleanTree(root) {
        var removed = false;

        Array.prototype.slice.call(root.querySelectorAll('script, noscript')).forEach(function (element) {
            element.parentNode.removeChild(element);
            removed = true;
        });
        Array.prototype.slice.call(root.querySelectorAll('*')).forEach(function (element) {
            var tagName = element.tagName.toLowerCase();
            Array.prototype.slice.call(element.attributes).forEach(function (attribute) {
                var name = attribute.name.toLowerCase();
                if (name.indexOf('on') === 0 || name === 'srcdoc'
                    || (URL_ATTRIBUTES.indexOf(name) !== -1 && (name !== 'value' || tagName === VALUE_URL_ELEMENT)
                        && SCRIPT_URL.test(attribute.value.replace(/[\x00-\x20\x7f]+/g, '')))) {
                    element.removeAttribute(attribute.name);
                    removed = true;
                }
            });
            if (tagName === 'template' && element.content && cleanTree(element.content)) {
                removed = true;
            }
        });

        return removed;
    }

    window.sw5csCleanEditorContent = clean;

    // Wraps initComponent of the editor field: the original runs unchanged first.
    var field = Ext.ClassManager.get('Shopware.form.field.TinyMCE').prototype;
    var originalInitComponent = field.initComponent;

    field.initComponent = function () {
        var me = this;
        var result = originalInitComponent.apply(me, arguments);

        me.on('rendereditor', function (component, editor) {
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
                    // createGrowlMessage() inserts the text as HTML.
                    Shopware.Notification.createGrowlMessage('', Ext.String.htmlEncode(window.sw5csEditorNotice), '');
                }
            });
        });

        return result;
    };
}());
</script>
{/literal}
{/if}
{/block}
