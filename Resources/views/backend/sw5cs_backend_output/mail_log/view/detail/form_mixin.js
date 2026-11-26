// This file is part of Sw5CommunitySecurity. (c) HQ GmbH, licensed under the GNU General Public License v3.0.
//{block name="backend/mail_log/view/detail/form_mixin" append}
// Subject, sender and recipients in the detail view are output as text, not as HTML.
Ext.define('Shopware.apps.MailLog.view.detail.Sw5csFormMixin', {
    override: 'Shopware.apps.MailLog.view.detail.FormMixin',

    createMetaDataFieldSet: function() {
        var fieldSet = this.callParent(arguments);

        Ext.each(['subject', 'sender'], function(name) {
            if (fieldSet.fields && fieldSet.fields[name]) {
                fieldSet.fields[name].htmlEncode = true;
            }
        });

        return fieldSet;
    },

    createRecipientsField: function(model, formField) {
        var field = this.callParent(arguments);

        if (field && field.xtype === 'displayfield') {
            field.htmlEncode = true;
        }

        return field;
    }
});
//{/block}
