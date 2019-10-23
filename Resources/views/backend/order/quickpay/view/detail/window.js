//{namespace name="plugins/quickpay"}
Ext.define('Shopware.apps.Order.QuickPay.view.detail.Window',
{
    override: 'Shopware.apps.Order.view.detail.Window',
    createTabPanel: function()
    {
        var me = this;
        var tab_panel = me.callParent(arguments);

        tab_panel.add(Ext.create('Shopware.apps.QuickPay.view.detail.QuickPay', {
            record: me.record,
            quickpayPaymentStore: Ext.getStore('quickpay-payment-store')
        }));
        
        return tab_panel;
    }
});