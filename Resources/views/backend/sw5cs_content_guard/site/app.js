// This file is part of Sw5CommunitySecurity. (c) HQ GmbH, licensed under the GNU General Public License v3.0.
//{block name="backend/site/app" append}
// The failure callback of Site/saveSite in the shop pages module shows no
// server message (themes/Backend/ExtJs/backend/site/controller/form.js), so a
// rejection by the content check would go unnoticed. Show it as a growl
// message; registered once, only for the plugin's own messages.
if (!Ext.sw5csSiteSaveMessage) {
    Ext.sw5csSiteSaveMessage = true;
    Ext.Ajax.on('requestcomplete', function(connection, response, options) {
        var data;

        if (!options || typeof options.url !== 'string' || options.url.indexOf('Site/saveSite') === -1) {
            return;
        }
        try {
            data = Ext.decode(response.responseText);
        } catch (e) {
            return;
        }
        if (data && data.success === false && typeof data.message === 'string' && data.message.indexOf('Community Security Guard') === 0) {
            Shopware.Notification.createGrowlMessage('', data.message, '');
        }
    });
}
//{/block}
