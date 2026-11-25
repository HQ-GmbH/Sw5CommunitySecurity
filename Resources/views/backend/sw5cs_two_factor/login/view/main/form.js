// This file is part of Sw5CommunitySecurity. (c) HQ GmbH, licensed under the GNU General Public License v3.0.
//{namespace name="backend/sw5_community_security/messages"}
//{block name="backend/login/view/main/form" append}
// Adds an optional field for the two-factor code below the password field.
Ext.define('Shopware.apps.Login.view.main.Sw5csTwoFactorForm', {
    override: 'Shopware.apps.Login.view.main.Form',

    initComponent: function() {
        var me = this;

        me.callParent(arguments);

        if (!me.password) {
            return;
        }

        me.insert(me.items.indexOf(me.password) + 1, Ext.create('Ext.form.field.Text', {
            name: 'sw5csTwoFactorCode',
            allowBlank: true,
            maxLength: 12,
            enforceMaxLength: true,
            emptyText: '{s name="two_factor_login_field"}Two-factor code (if set up){/s}'
        }));
    }
});
//{/block}
