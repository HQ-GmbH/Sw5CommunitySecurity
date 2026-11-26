// This file is part of Sw5CommunitySecurity. (c) HQ GmbH, licensed under the GNU General Public License v3.0.
//{block name="backend/mail_log/view/list/mail_log" append}
// Subject, sender and recipients of logged mails are output as text, not as HTML.
Ext.define('Shopware.apps.MailLog.view.list.Sw5csMailLog', {
    override: 'Shopware.apps.MailLog.view.list.MailLog',

    configure: function() {
        var config = this.callParent(arguments);

        if (config && config.columns && config.columns.subject) {
            config.columns.subject.renderer = this.sw5csTextRenderer;
        }

        return config;
    },

    sw5csTextRenderer: function(value) {
        return Ext.String.htmlEncode(value);
    },

    contactColumnRenderer: function(contacts) {
        var encode = Ext.String.htmlEncode,
            link = function(address) {
                return '<a href="mailto:' + encode(address) + '">' + encode(address) + '</a>';
            };

        if (typeof contacts === 'string') {
            return link(contacts);
        }

        return (contacts || []).map(function(contact) {
            return link(contact.mailAddress);
        }).join('&nbsp;');
    }
});
//{/block}
