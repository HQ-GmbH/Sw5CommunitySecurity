// This file is part of Sw5CommunitySecurity. (c) HQ GmbH, licensed under the GNU General Public License v3.0.
//{block name="backend/mail_log/view/list/mail_log" append}
// Subject, sender and recipients of logged mails are output as text, not as
// HTML. The original renderers are wrapped and receive encoded addresses.
Ext.define('Shopware.apps.MailLog.view.list.Sw5csMailLog', {
    override: 'Shopware.apps.MailLog.view.list.MailLog',

    configure: function() {
        var me = this,
            config = me.callParent(arguments),
            columns = config && config.columns;

        if (columns && columns.subject) {
            columns.subject.renderer = me.sw5csTextRenderer;
        }
        Ext.each(['sender', 'recipients'], function(name) {
            if (columns && columns[name] && typeof columns[name].renderer === 'function') {
                columns[name].renderer = me.sw5csContactRenderer(columns[name].renderer);
            }
        });

        return config;
    },

    sw5csTextRenderer: function(value) {
        return Ext.String.htmlEncode(value);
    },

    // Renderer receiving the contact (string) or the contacts (objects with
    // mailAddress) encoded; other arguments unchanged.
    sw5csContactRenderer: function(renderer) {
        var encode = function(value) {
            return typeof value === 'string' ? Ext.String.htmlEncode(value) : value;
        };

        return function(contacts) {
            var args = Array.prototype.slice.call(arguments);

            if (Ext.isArray(contacts)) {
                args[0] = Ext.Array.map(contacts, function(contact) {
                    var copy;

                    if (!contact || typeof contact !== 'object') {
                        return encode(contact);
                    }
                    copy = Object.create(contact);
                    copy.mailAddress = encode(contact.mailAddress);

                    return copy;
                });
            } else {
                args[0] = encode(contacts);
            }

            return renderer.apply(this, args);
        };
    }
});
//{/block}
