// This file is part of Sw5CommunitySecurity. (c) HQ GmbH, licensed under the GNU General Public License v3.0.
//{block name="backend/mail_log/view/detail/form_mixin" append}
// Subject, sender and recipients in the detail view are output as text, not
// as HTML. Ext JS encodes the value (htmlEncode, Ext.form.field.Display.getDisplayValue);
// allowHtml keeps Shopware's override (overrides/Ext.form.field.Display.js)
// from decoding it again (Ext.String.getText), so it is encoded exactly once.
Ext.define('Shopware.apps.MailLog.view.detail.Sw5csFormMixin', {
    override: 'Shopware.apps.MailLog.view.detail.FormMixin',

    createMetaDataFieldSet: function() {
        var me = this,
            fieldSet = me.callParent(arguments);

        Ext.each(['subject', 'sender'], function(name) {
            if (fieldSet && fieldSet.fields && fieldSet.fields[name]) {
                me.sw5csEncodeDisplayField(fieldSet.fields[name]);
            }
        });

        return fieldSet;
    },

    createRecipientsField: function(model, formField) {
        var field = this.callParent(arguments);

        if (field && field.xtype === 'displayfield') {
            this.sw5csEncodeDisplayField(field);
        }

        return field;
    },

    sw5csEncodeDisplayField: function(field) {
        field.htmlEncode = true;
        field.allowHtml = true;
    }
});
//{/block}
