// This file is part of Sw5CommunitySecurity. (c) HQ GmbH, licensed under the GNU General Public License v3.0.
//{block name="backend/mail/view/main/info" append}
// The variable list shows values stored with the last mail sent from this
// template. Output them as text, not as HTML: the original template is used
// unchanged, only its simple placeholders get the format "htmlEncode".
Ext.define('Shopware.apps.Mail.view.main.Sw5csInfo', {
    override: 'Shopware.apps.Mail.view.main.Info',

    getTemplate: function() {
        var template = this.callParent(arguments);

        if (!Ext.isArray(template)) {
            return template;
        }

        return Ext.Array.map(template, function(part) {
            // {literal}
            return typeof part === 'string' ? part.replace(/\{(\.|[A-Za-z_][\w.\-]*)\}/g, '{$1:htmlEncode}') : part;
            // {/literal}
        });
    }
});
//{/block}
