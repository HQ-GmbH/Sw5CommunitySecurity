// This file is part of Sw5CommunitySecurity. (c) HQ GmbH, licensed under the GNU General Public License v3.0.
//{block name="backend/mail/view/main/info" append}
// The variable list shows values stored with the last mail sent from this
// template. Output them as text, not as HTML.
Ext.define('Shopware.apps.Mail.view.main.Sw5csInfo', {
    override: 'Shopware.apps.Mail.view.main.Info',

    getTemplate: function() {
        return [
            '{literal}',
            '<pre style="margin: 10px">',
            '<tpl for=".">',
            '<div>{name:htmlEncode}: {value:htmlEncode}</div>',
            '</tpl>',
            '</pre>',
            '{/literal}'
        ];
    }
});
//{/block}
